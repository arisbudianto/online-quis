<?php

namespace App\Http\Controllers\Dosen;

use App\Exports\LiveSessionExport;
use App\Http\Controllers\Controller;
use App\Models\LiveAnswer;
use App\Models\LivePlayer;
use App\Models\LiveSession;
use App\Models\Quiz;
use App\Services\LiveGameService;
use App\Support\LiveStateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Sisi dosen kuis interaktif: membuat sesi, memandu, melihat hasil. */
class LiveSessionController extends Controller
{
    public function __construct(private LiveGameService $game)
    {
    }

    public function index(Request $request)
    {
        $sessions = LiveSession::with('quiz:id,title')
            ->withCount('players')
            ->when($request->user()->role !== 'admin', fn ($q) => $q->where('host_id', $request->user()->id))
            ->latest()
            ->paginate(20);

        return view('dosen.live.index', compact('sessions'));
    }

    public function create(Request $request)
    {
        $quiz = Quiz::findOrFail($request->integer('quiz'));
        $this->authorizeQuiz($request, $quiz);

        $mode = $request->query('mode') === LiveSession::MODE_CHALLENGE ? LiveSession::MODE_CHALLENGE : LiveSession::MODE_LIVE;
        $eligible = $this->game->eligibleQuestions($quiz)->count();
        $total = $quiz->questions()->count();

        return view('dosen.live.create', compact('quiz', 'mode', 'eligible', 'total'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'quiz_id' => ['required', 'integer', 'exists:quizzes,id'],
            'mode' => ['required', Rule::in([LiveSession::MODE_LIVE, LiveSession::MODE_CHALLENGE])],
            'seconds_per_question' => ['required', 'integer', Rule::in(LiveSession::SECONDS_OPTIONS)],
            'shuffle' => ['nullable', 'boolean'],
        ]);

        $quiz = Quiz::findOrFail($data['quiz_id']);
        $this->authorizeQuiz($request, $quiz);

        $session = $this->game->createSession(
            $quiz,
            $request->user(),
            $data['mode'],
            (int) $data['seconds_per_question'],
            (bool) ($data['shuffle'] ?? false)
        );

        return redirect()->route('dosen.live.show', $session);
    }

    /** Layar proyektor. */
    public function show(Request $request, LiveSession $session)
    {
        $this->authorizeSession($request, $session);
        $session->load('quiz:id,title');

        return view($session->isLive() ? 'live.host-live' : 'live.host-challenge', [
            'session' => $session,
            'joinUrl' => route('live.join', $session->code),
            'stateUrl' => app(LiveStateFile::class)->url($session->state_token),
        ]);
    }

    public function qr(Request $request, LiveSession $session)
    {
        $this->authorizeSession($request, $session);

        $svg = QrCode::format('svg')->size(360)->margin(1)->generate(route('live.join', $session->code));

        return response($svg)->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'private, max-age=3600');
    }

    /** Data yang tidak ada di file status: daftar peserta dan jumlah yang sudah menjawab. */
    public function status(Request $request, LiveSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        $questionId = $this->game->currentQuestionId($session);

        return response()->json([
            'status' => $session->status,
            'index' => $session->current_index,
            'players' => $session->players()->orderBy('id')->get(['id', 'nickname'])->toArray(),
            'answered' => $questionId && $session->isLive()
                ? LiveAnswer::where('live_session_id', $session->id)->where('question_id', $questionId)->count()
                : null,
            'leaderboard' => $session->isLive() ? null : $this->game->leaderboard($session),
            'awards' => !$session->isLive() && $session->isFinished() ? $this->game->awards($session) : [],
            'total' => $session->questionCount(),
        ])->header('Cache-Control', 'no-store');
    }

    public function start(Request $request, LiveSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);
        abort_unless($session->status === 'lobby', 409);
        $this->game->startQuestion($session, 0);

        return $this->ok($session);
    }

    public function reveal(Request $request, LiveSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        // Abaikan permintaan ganda untuk soal yang sudah ditutup (mis. timer
        // dan tombol "Tutup" terpicu bersamaan).
        if ($request->filled('index') && $request->integer('index') !== $session->current_index) {
            return $this->ok($session);
        }

        $this->game->reveal($session);

        return $this->ok($session);
    }

    public function next(Request $request, LiveSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);
        abort_unless($session->status === 'reveal', 409);
        $this->game->next($session);

        return $this->ok($session);
    }

    public function finish(Request $request, LiveSession $session)
    {
        $this->authorizeSession($request, $session);
        $this->game->finish($session);

        return $request->expectsJson()
            ? $this->ok($session)
            : redirect()->route('dosen.live.results', $session)->with('status', 'Sesi diakhiri.');
    }

    public function kick(Request $request, LiveSession $session, LivePlayer $player): JsonResponse
    {
        $this->authorizeSession($request, $session);
        $this->game->kick($session, $player);

        return $this->ok($session);
    }

    public function results(Request $request, LiveSession $session)
    {
        $this->authorizeSession($request, $session);
        $session->load('quiz:id,title');

        $leaderboard = $session->ranking()->with('user:id,nim,kelas')->get();

        $answers = LiveAnswer::where('live_session_id', $session->id)
            ->selectRaw('question_id, count(*) as total, sum(case when is_correct then 1 else 0 end) as benar, avg(response_ms) as rata_ms')
            ->groupBy('question_id')->get()->keyBy('question_id');

        $questions = \App\Models\Question::whereIn('id', collect($session->plan)->pluck('q'))->get()->keyBy('id');
        $perQuestion = collect($session->plan)->values()->map(fn ($step, $i) => [
            'no' => $i + 1,
            'text' => $questions[$step['q']]->question_text ?? '(soal dihapus)',
            'total' => (int) ($answers[$step['q']]->total ?? 0),
            'benar' => (int) ($answers[$step['q']]->benar ?? 0),
            'rata_detik' => isset($answers[$step['q']]) ? round($answers[$step['q']]->rata_ms / 1000, 1) : null,
        ]);

        return view('dosen.live.results', compact('session', 'leaderboard', 'perQuestion'));
    }

    public function export(Request $request, LiveSession $session)
    {
        $this->authorizeSession($request, $session);

        $name = 'latihan-' . str($session->quiz->title ?? 'kuis')->slug() . '-' . $session->created_at->format('Ymd-Hi') . '.xlsx';

        return Excel::download(new LiveSessionExport($session), $name);
    }

    /** Hapus sesi beserta peserta dan jawabannya (data latihan, tidak masuk nilai). */
    public function destroy(Request $request, LiveSession $session)
    {
        $this->authorizeSession($request, $session);

        $label = $session->modeLabel() . ' ' . $session->code;
        app(LiveStateFile::class)->delete($session->state_token);
        $session->delete(); // peserta dan jawaban ikut terhapus (cascade)

        return redirect()->route('dosen.live.index')->with('status', "Sesi {$label} dihapus.");
    }

    private function ok(LiveSession $session): JsonResponse
    {
        $session->refresh();

        return response()->json(['status' => $session->status, 'index' => $session->current_index, 'v' => $session->version]);
    }

    private function authorizeQuiz(Request $request, Quiz $quiz): void
    {
        $user = $request->user();
        abort_unless($user->role === 'admin' || $quiz->dosen_id === $user->id, 403);
    }

    private function authorizeSession(Request $request, LiveSession $session): void
    {
        $user = $request->user();
        abort_unless($user->role === 'admin' || $session->host_id === $user->id, 403);
    }
}
