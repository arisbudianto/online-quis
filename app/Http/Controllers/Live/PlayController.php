<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Models\LivePlayer;
use App\Models\LiveSession;
use App\Services\LiveGameService;
use App\Support\LiveStateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sisi peserta kuis interaktif. Tidak wajib login: peserta cukup scan QR,
 * isi nama panggilan, lalu main. Peserta dikenali lewat sesi browser.
 */
class PlayController extends Controller
{
    public function __construct(private LiveGameService $game)
    {
    }

    public function enter(): View
    {
        return view('live.enter');
    }

    public function enterSubmit(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);

        return redirect()->route('live.join', strtoupper(trim($data['code'])));
    }

    public function join(Request $request, string $code): View|RedirectResponse
    {
        $session = $this->session($code);

        if (!$session) {
            return redirect()->route('live.enter')->withErrors(['code' => 'Kode sesi tidak ditemukan. Periksa lagi kodenya.']);
        }

        if ($this->player($request, $session)) {
            return redirect()->route('live.play', $session->code);
        }

        return view('live.join', ['session' => $session->load('quiz:id,title'), 'user' => $request->user()]);
    }

    public function joinSubmit(Request $request, string $code): RedirectResponse
    {
        $session = $this->session($code);
        abort_unless($session, 404);

        $data = $request->validate([
            'nickname' => ['required', 'string', 'min:2', 'max:30'],
        ], [], ['nickname' => 'nama']);

        $user = $request->user();
        $player = $this->game->join($session, $user && $user->role === 'mahasiswa' ? $user : null, $data['nickname']);

        $request->session()->put($this->key($session), $player->id);

        return redirect()->route('live.play', $session->code);
    }

    public function play(Request $request, string $code): View|RedirectResponse
    {
        $session = $this->session($code);
        abort_unless($session, 404);

        $player = $this->player($request, $session);
        if (!$player) {
            return redirect()->route('live.join', $session->code)
                ->withErrors(['nickname' => 'Silakan isi nama untuk bergabung.']);
        }

        return view($session->isLive() ? 'live.play-live' : 'live.play-challenge', [
            'session' => $session->load('quiz:id,title'),
            'player' => $player,
            'stateUrl' => app(LiveStateFile::class)->url($session->state_token),
            'pollMs' => max(500, (int) config('live.poll_ms', 1000)),
        ]);
    }

    public function clock(): JsonResponse
    {
        return response()->json(['now_ms' => LiveGameService::nowMs()])->header('Cache-Control', 'no-store');
    }

    /** Mode live: kirim jawaban. */
    public function answer(Request $request, string $code): JsonResponse
    {
        [$session, $player] = $this->context($request, $code);
        abort_unless($session->isLive(), 404);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['required', 'integer'],
            'elapsed_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ]);

        $this->game->answerLive($session, $player, (int) $data['question_id'], (int) $data['option_id'], $data['elapsed_ms'] ?? null);

        return response()->json(['received' => true]);
    }

    /** Skor dan peringkat pribadi (dipakai saat pembahasan dan akhir sesi). */
    public function me(Request $request, string $code): JsonResponse
    {
        [$session, $player] = $this->context($request, $code);

        $questionId = $request->integer('question_id') ?: null;
        $last = $questionId ? $player->answers()->where('question_id', $questionId)->first() : null;

        return response()->json([
            'nickname' => $player->nickname,
            'score' => $player->score,
            'streak' => $player->streak,
            'correct' => $player->correct_count,
            'rank' => $this->game->rankOf($session, $player),
            'players' => $session->players()->count(),
            'last' => $questionId ? [
                'answered' => (bool) $last,
                'correct' => (bool) $last?->is_correct,
                'points' => (int) ($last?->points ?? 0),
            ] : null,
        ])->header('Cache-Control', 'no-store');
    }

    /** Mode tantangan: soal saat ini. */
    public function challengeQuestion(Request $request, string $code): JsonResponse
    {
        [$session, $player] = $this->context($request, $code);
        abort_unless(!$session->isLive(), 404);

        $current = $this->game->challengeCurrent($session, $player);

        return response()->json($current ?? ['finished' => true, 'closed' => $session->isFinished()])
            ->header('Cache-Control', 'no-store');
    }

    /** Mode tantangan: kirim jawaban (option_id kosong = waktu habis). */
    public function challengeAnswer(Request $request, string $code): JsonResponse
    {
        [$session, $player] = $this->context($request, $code);
        abort_unless(!$session->isLive(), 404);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->game->answerChallenge(
            $session,
            $player,
            (int) $data['question_id'],
            isset($data['option_id']) ? (int) $data['option_id'] : null
        ));
    }

    /** Papan peringkat untuk peserta (10 besar + posisinya sendiri). */
    public function leaderboard(Request $request, string $code): JsonResponse
    {
        [$session, $player] = $this->context($request, $code);

        return response()->json([
            'top' => $this->game->leaderboard($session, 10),
            'me' => ['id' => $player->id, 'rank' => $this->game->rankOf($session, $player), 'score' => $player->score],
            'players' => $session->players()->count(),
        ])->header('Cache-Control', 'no-store');
    }

    private function session(string $code): ?LiveSession
    {
        return LiveSession::where('code', strtoupper($code))->first();
    }

    private function key(LiveSession $session): string
    {
        return 'live_player.' . $session->id;
    }

    private function player(Request $request, LiveSession $session): ?LivePlayer
    {
        $id = $request->session()->get($this->key($session));

        $player = $id ? LivePlayer::where('live_session_id', $session->id)->find($id) : null;

        // Mahasiswa yang login dan sudah bergabung dari perangkat lain.
        if (!$player && $request->user()?->role === 'mahasiswa') {
            $player = $session->players()->where('user_id', $request->user()->id)->first();
            if ($player) {
                $request->session()->put($this->key($session), $player->id);
            }
        }

        return $player;
    }

    /** @return array{0: LiveSession, 1: LivePlayer} */
    private function context(Request $request, string $code): array
    {
        $session = $this->session($code);
        abort_unless($session, 404);

        $player = $this->player($request, $session);
        abort_unless($player, 403, 'Anda belum bergabung atau sudah dikeluarkan dari sesi ini.');

        return [$session, $player];
    }
}
