<?php

namespace App\Services;

use App\Models\LiveAnswer;
use App\Models\LivePlayer;
use App\Models\LiveSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Support\LiveStateFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan main kuis interaktif (latihan, tidak masuk nilai semester).
 *
 * Mode live: dosen memandu. Status sesi ditulis ke file JSON statis
 * (LiveStateFile) yang dibaca HP siswa tiap detik, supaya shared hosting
 * tidak menjalankan PHP untuk setiap polling.
 *
 * Mode tantangan: siswa mengerjakan sendiri dengan urutan acak pribadi.
 *
 * Poin jawaban benar: 500 + bonus kecepatan sampai 500 + bonus beruntun
 * 100 per jawaban benar berturut-turut setelah yang pertama (maks 500).
 */
class LiveGameService
{
    public const BASE_POINTS = 500;
    public const SPEED_POINTS = 500;
    public const STREAK_BONUS = 100;
    public const STREAK_BONUS_MAX = 500;

    /** Toleransi jaringan untuk jawaban yang tiba sedikit setelah waktu habis. */
    public const GRACE_MS = 1500;
    /** Jawaban tidak boleh mengaku lebih cepat dari ini dibanding jam server (jeda polling). */
    public const CLIENT_LAG_ALLOWANCE_MS = 2500;

    public function __construct(private LiveStateFile $stateFile)
    {
    }

    public static function nowMs(): int
    {
        return (int) now()->getTimestampMs();
    }

    /** Soal yang bisa dimainkan: pilihan ganda dengan minimal dua opsi dan satu kunci. */
    public function eligibleQuestions(Quiz $quiz): Collection
    {
        return $quiz->questions()
            ->where('type', 'pilihan_ganda')
            ->with(['options' => fn ($q) => $q->orderBy('order')->orderBy('id')])
            ->orderBy('order')->orderBy('id')
            ->get()
            ->filter(fn (Question $q) => $q->options->count() >= 2 && $q->options->contains('is_correct', true))
            ->values();
    }

    public function createSession(Quiz $quiz, User $host, string $mode, int $seconds, bool $shuffleQuestions): LiveSession
    {
        $questions = $this->eligibleQuestions($quiz);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'quiz' => 'Kuis ini belum punya soal pilihan ganda dengan kunci jawaban.',
            ]);
        }

        $session = LiveSession::create([
            'quiz_id' => $quiz->id,
            'host_id' => $host->id,
            'mode' => $mode,
            'code' => LiveSession::generateUniqueCode(),
            'state_token' => LiveSession::newStateToken(),
            'status' => $mode === LiveSession::MODE_LIVE ? 'lobby' : 'open',
            'seconds_per_question' => $seconds,
            'plan' => $this->buildPlan($questions, $shuffleQuestions),
            'started_at' => $mode === LiveSession::MODE_CHALLENGE ? now() : null,
        ]);

        if ($session->isLive()) {
            $this->publish($session);
        }

        return $session;
    }

    /** @return array<int, array{q: int, o: array<int, int>}> */
    private function buildPlan(Collection $questions, bool $shuffleQuestions): array
    {
        $list = $shuffleQuestions ? $questions->shuffle() : $questions;

        return $list->map(fn (Question $q) => [
            'q' => $q->id,
            'o' => $q->options->pluck('id')->shuffle()->values()->all(),
        ])->values()->all();
    }

    // ------------------------------------------------------------------
    // Peserta
    // ------------------------------------------------------------------

    public function join(LiveSession $session, ?User $user, string $nickname): LivePlayer
    {
        if ($session->isFinished()) {
            throw ValidationException::withMessages(['nickname' => 'Sesi ini sudah selesai.']);
        }

        $nickname = trim(preg_replace('/\s+/u', ' ', $nickname));

        if ($user) {
            $existing = $session->players()->where('user_id', $user->id)->first();
            if ($existing) {
                return $existing;
            }
        }

        $taken = $session->players()->whereRaw('LOWER(nickname) = ?', [mb_strtolower($nickname)])->exists();
        if ($taken) {
            throw ValidationException::withMessages(['nickname' => 'Nama ini sudah dipakai peserta lain. Pakai nama lain.']);
        }

        $player = $session->players()->create([
            'user_id' => $user?->id,
            'nickname' => $nickname,
            'plan' => $session->isLive() ? null : $this->buildPlan(
                $this->questionsFromPlan($session),
                true
            ),
        ]);

        if ($session->isLive()) {
            $this->publish($session);
        }

        return $player;
    }

    public function kick(LiveSession $session, LivePlayer $player): void
    {
        abort_unless($player->live_session_id === $session->id, 404);
        $player->delete();

        if ($session->isLive()) {
            $this->publish($session);
        }
    }

    // ------------------------------------------------------------------
    // Mode live: kendali dosen
    // ------------------------------------------------------------------

    public function startQuestion(LiveSession $session, int $index): void
    {
        abort_unless($session->isLive() && !$session->isFinished(), 409);
        abort_unless($index >= 0 && $index < $session->questionCount(), 409);

        $session->forceFill([
            'status' => 'question',
            'current_index' => $index,
            'question_started_ms' => self::nowMs(),
            'started_at' => $session->started_at ?? now(),
        ])->save();

        $this->publish($session);
    }

    public function next(LiveSession $session): void
    {
        $nextIndex = $session->current_index + 1;

        if ($nextIndex >= $session->questionCount()) {
            $this->finish($session);

            return;
        }

        $this->startQuestion($session, $nextIndex);
    }

    /** Tutup soal yang sedang berjalan dan tampilkan pembahasannya. */
    public function reveal(LiveSession $session): void
    {
        if ($session->status !== 'question') {
            return;
        }

        $questionId = $this->currentQuestionId($session);

        // Yang tidak menjawab kehilangan rangkaian beruntun.
        $session->players()
            ->whereNotIn('id', LiveAnswer::where('question_id', $questionId)
                ->where('live_session_id', $session->id)->select('live_player_id'))
            ->update(['streak' => 0]);

        $session->forceFill(['status' => 'reveal'])->save();
        $this->publish($session);
    }

    public function finish(LiveSession $session): void
    {
        if ($session->isFinished()) {
            return;
        }

        if ($session->status === 'question') {
            $this->reveal($session);
        }

        $session->forceFill(['status' => 'finished', 'finished_at' => now()])->save();

        if ($session->isLive()) {
            $this->publish($session);
        }
    }

    // ------------------------------------------------------------------
    // Jawaban
    // ------------------------------------------------------------------

    /** Mode live: kunci tidak dikirim balik; hasil dilihat saat pembahasan. */
    public function answerLive(LiveSession $session, LivePlayer $player, int $questionId, int $optionId, ?int $clientElapsedMs): LiveAnswer
    {
        if ($session->status !== 'question' || $this->currentQuestionId($session) !== $questionId) {
            throw ValidationException::withMessages(['answer' => 'Soal ini sudah ditutup.']);
        }

        $step = $session->plan[$session->current_index];
        if (!in_array($optionId, $step['o'], true)) {
            throw ValidationException::withMessages(['answer' => 'Pilihan tidak valid.']);
        }

        $limitMs = $session->seconds_per_question * 1000;
        $serverElapsed = self::nowMs() - (int) $session->question_started_ms;

        if ($serverElapsed > $limitMs + self::GRACE_MS) {
            throw ValidationException::withMessages(['answer' => 'Waktu menjawab sudah habis.']);
        }

        $elapsed = $clientElapsedMs === null
            ? $serverElapsed
            : max($serverElapsed - self::CLIENT_LAG_ALLOWANCE_MS, min($clientElapsedMs, $serverElapsed));

        return $this->record($session, $player, $questionId, $optionId, $elapsed, $limitMs);
    }

    /**
     * Mode tantangan: soal berikutnya untuk pemain ini, atau null bila selesai.
     *
     * @return array{index: int, total: int, question: array, seconds: int, remaining_ms: int}|null
     */
    public function challengeCurrent(LiveSession $session, LivePlayer $player): ?array
    {
        $plan = $player->plan ?? [];

        if ($session->isFinished() || $player->current_index >= count($plan)) {
            $this->markPlayerFinished($player);

            return null;
        }

        if ($player->served_ms === null) {
            $player->forceFill(['served_ms' => self::nowMs()])->save();
        }

        $step = $plan[$player->current_index];
        $limitMs = $session->seconds_per_question * 1000;

        return [
            'index' => $player->current_index,
            'total' => count($plan),
            'question' => $this->questionPayload($step),
            'seconds' => $session->seconds_per_question,
            'remaining_ms' => max(0, $limitMs - (self::nowMs() - (int) $player->served_ms)),
        ];
    }

    /**
     * Mode tantangan: catat jawaban (null = waktu habis) dan kembalikan umpan balik.
     *
     * @return array{correct: bool, correct_option_id: int, points: int, score: int, streak: int, finished: bool}
     */
    public function answerChallenge(LiveSession $session, LivePlayer $player, int $questionId, ?int $optionId): array
    {
        if ($session->isFinished()) {
            throw ValidationException::withMessages(['answer' => 'Tantangan sudah ditutup dosen.']);
        }

        $plan = $player->plan ?? [];
        $step = $plan[$player->current_index] ?? null;

        if (!$step || $step['q'] !== $questionId || $player->served_ms === null) {
            throw ValidationException::withMessages(['answer' => 'Soal ini bukan soal yang sedang dikerjakan.']);
        }

        if ($optionId !== null && !in_array($optionId, $step['o'], true)) {
            throw ValidationException::withMessages(['answer' => 'Pilihan tidak valid.']);
        }

        $limitMs = $session->seconds_per_question * 1000;
        $elapsed = self::nowMs() - (int) $player->served_ms;

        // Lewat batas waktu (plus toleransi jaringan) dihitung tidak menjawab.
        if ($elapsed > $limitMs + self::GRACE_MS) {
            $optionId = null;
        }

        $answer = $this->record($session, $player, $questionId, $optionId, min($elapsed, $limitMs), $limitMs);

        $player->refresh();
        $player->forceFill([
            'current_index' => $player->current_index + 1,
            'served_ms' => null,
        ])->save();

        $finished = $player->current_index >= count($plan);
        if ($finished) {
            $this->markPlayerFinished($player);
        }

        return [
            'correct' => $answer->is_correct,
            'correct_option_id' => $this->correctOptionId($questionId),
            'points' => $answer->points,
            'score' => $player->score,
            'streak' => $player->streak,
            'finished' => $finished,
        ];
    }

    private function record(LiveSession $session, LivePlayer $player, int $questionId, ?int $optionId, int $elapsedMs, int $limitMs): LiveAnswer
    {
        return DB::transaction(function () use ($session, $player, $questionId, $optionId, $elapsedMs, $limitMs) {
            $player = LivePlayer::whereKey($player->id)->lockForUpdate()->firstOrFail();

            if (LiveAnswer::where('live_player_id', $player->id)->where('question_id', $questionId)->exists()) {
                throw ValidationException::withMessages(['answer' => 'Soal ini sudah dijawab.']);
            }

            $correct = $optionId !== null && $optionId === $this->correctOptionId($questionId);
            $elapsedMs = max(0, min($elapsedMs, $limitMs));
            $streak = $correct ? $player->streak + 1 : 0;
            $points = $correct ? self::points($elapsedMs, $limitMs, $streak) : 0;

            $answer = LiveAnswer::create([
                'live_session_id' => $session->id,
                'live_player_id' => $player->id,
                'question_id' => $questionId,
                'option_id' => $optionId,
                'is_correct' => $correct,
                'response_ms' => $elapsedMs,
                'points' => $points,
            ]);

            $player->forceFill([
                'score' => $player->score + $points,
                'streak' => $streak,
                'best_streak' => max($player->best_streak, $streak),
                'correct_count' => $player->correct_count + ($correct ? 1 : 0),
                'answered_count' => $player->answered_count + ($optionId !== null ? 1 : 0),
                'total_response_ms' => $player->total_response_ms + $elapsedMs,
            ])->save();

            return $answer;
        });
    }

    public static function points(int $elapsedMs, int $limitMs, int $streak): int
    {
        $speed = $limitMs > 0 ? (int) round(self::SPEED_POINTS * (1 - $elapsedMs / $limitMs)) : 0;
        $bonus = min(max(0, $streak - 1) * self::STREAK_BONUS, self::STREAK_BONUS_MAX);

        return self::BASE_POINTS + max(0, $speed) + $bonus;
    }

    private function markPlayerFinished(LivePlayer $player): void
    {
        if ($player->finished_at === null) {
            $player->forceFill(['finished_at' => now(), 'served_ms' => null])->save();
        }
    }

    // ------------------------------------------------------------------
    // Data tampilan
    // ------------------------------------------------------------------

    public function currentQuestionId(LiveSession $session): ?int
    {
        return $session->plan[$session->current_index]['q'] ?? null;
    }

    /** @var array<int, int|null> */
    private array $correctCache = [];

    private function correctOptionId(int $questionId): ?int
    {
        if (!array_key_exists($questionId, $this->correctCache)) {
            $this->correctCache[$questionId] = \App\Models\Option::where('question_id', $questionId)
                ->where('is_correct', true)->orderBy('id')->value('id');
        }

        return $this->correctCache[$questionId];
    }

    private function questionsFromPlan(LiveSession $session): Collection
    {
        $ids = collect($session->plan)->pluck('q');
        $questions = Question::with('options')->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => $questions[$id] ?? null)->filter()->values();
    }

    /** Soal tanpa kunci jawaban, opsi sesuai urutan di plan. */
    public function questionPayload(array $step): array
    {
        $question = Question::with('options')->findOrFail($step['q']);
        $options = $question->options->keyBy('id');

        return [
            'id' => $question->id,
            'text' => $question->question_text,
            'image' => $question->image_path ? asset($question->image_path) : null,
            'options' => collect($step['o'])
                ->filter(fn ($id) => isset($options[$id]))
                ->map(fn ($id) => ['id' => $id, 'text' => $options[$id]->option_text])
                ->values()->all(),
        ];
    }

    /** @return array<int, array{rank: int, nickname: string, score: int, correct: int}> */
    public function leaderboard(LiveSession $session, ?int $limit = null): array
    {
        $query = $session->ranking();
        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->values()->map(fn (LivePlayer $p, $i) => [
            'rank' => $i + 1,
            'id' => $p->id,
            'nickname' => $p->nickname,
            'score' => $p->score,
            'correct' => $p->correct_count,
            'answered' => $p->answered_count,
            'progress' => $p->current_index,
            'finished' => $p->finished_at !== null,
        ])->all();
    }

    public function rankOf(LiveSession $session, LivePlayer $player): int
    {
        return $session->players()
            ->where(function ($q) use ($player) {
                $q->where('score', '>', $player->score)
                    ->orWhere(function ($q) use ($player) {
                        $q->where('score', $player->score)->where(function ($q) use ($player) {
                            $q->where('total_response_ms', '<', $player->total_response_ms)
                                ->orWhere(fn ($q) => $q->where('total_response_ms', $player->total_response_ms)->where('id', '<', $player->id));
                        });
                    });
            })->count() + 1;
    }

    /** Isi file status statis yang dibaca HP siswa dan layar dosen. */
    public function statePayload(LiveSession $session): array
    {
        $payload = [
            'v' => $session->version,
            'status' => $session->status,
            'index' => $session->current_index,
            'total' => $session->questionCount(),
            'seconds' => $session->seconds_per_question,
            'players' => $session->players()->count(),
        ];

        if (in_array($session->status, ['question', 'reveal'], true)) {
            $step = $session->plan[$session->current_index];
            $payload['question'] = $this->questionPayload($step);
            $payload['started_ms'] = (int) $session->question_started_ms;
        }

        if ($session->status === 'reveal') {
            $questionId = $step['q'];
            $counts = LiveAnswer::where('live_session_id', $session->id)
                ->where('question_id', $questionId)
                ->whereNotNull('option_id')
                ->selectRaw('option_id, count(*) as n')->groupBy('option_id')
                ->pluck('n', 'option_id');

            $payload['reveal'] = [
                'correct_option_id' => $this->correctOptionId($questionId),
                'counts' => collect($step['o'])->mapWithKeys(fn ($id) => [(string) $id => (int) ($counts[$id] ?? 0)])->all(),
                'answered' => (int) $counts->sum(),
                'top' => $this->leaderboard($session, 5),
            ];
        }

        if ($session->status === 'finished') {
            $payload['top'] = $this->leaderboard($session, 10);
        }

        return $payload;
    }

    /** Naikkan versi lalu tulis ulang file status (mode live). */
    public function publish(LiveSession $session): void
    {
        $session->increment('version');
        $session->refresh();
        $this->stateFile->write($session->state_token, $this->statePayload($session));
    }
}
