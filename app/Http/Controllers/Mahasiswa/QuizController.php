<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Quiz;
use App\Models\User;
use App\Services\AttemptFinalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $quizzes = Quiz::where('is_published', true)
            ->withCount('questions')
            // Kuis privat (is_public = false) sengaja TIDAK ikut di sini --
            // hanya bisa diakses lewat "Masukkan Kode Kuis" / scan QR.
            ->where('is_public', true)
            ->where(function ($q) use ($request) {
                $q->whereNull('target_kelas')
                  ->orWhereJsonContains('target_kelas', $request->user()->kelas);
            })
            ->orderBy('start_time')
            ->get();

        $attemptCounts = Attempt::where('user_id', $request->user()->id)
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->selectRaw('quiz_id, count(*) as total')
            ->groupBy('quiz_id')
            ->pluck('total', 'quiz_id');

        // Kuis yang attempt-nya masih berjalan: tampilkan tombol lanjutkan.
        $ongoingQuizIds = Attempt::where('user_id', $request->user()->id)
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->where('status', 'in_progress')
            ->pluck('quiz_id')
            ->flip();

        return view('mahasiswa.quizzes.index', compact('quizzes', 'attemptCounts', 'ongoingQuizIds'));
    }

    public function start(Request $request, Quiz $quiz)
    {
        $user = $request->user();

        // Kuis privat hanya boleh dimulai oleh pemegang kode. Tanpa cek ini,
        // siapa pun bisa POST ke /kuis/{id}/mulai dengan ID berurutan.
        if (!$quiz->is_public) {
            $givenCode = strtoupper(trim((string) $request->input('code')));
            abort_unless(
                !empty($quiz->code) && hash_equals($quiz->code, $givenCode),
                403,
                'Kuis privat hanya bisa dimulai lewat kode kuis.'
            );
        }

        $questionIds = $quiz->questions()->pluck('id')->all();

        if ($quiz->shuffle_questions) {
            shuffle($questionIds);
        }

        if ($quiz->questions_to_show && $quiz->questions_to_show < count($questionIds)) {
            $questionIds = array_slice($questionIds, 0, $quiz->questions_to_show);
        }

        if (empty($questionIds)) {
            return $this->backWithError($request, $quiz, 'Kuis ini belum berisi soal. Hubungi dosen pengampu.');
        }

        // Token perangkat yang stabil -- dipakai ulang kalau browser ini
        // sudah pernah punya (misal sedang/baru selesai kuis lain), atau
        // dibuat baru kalau belum ada. Disimpan sebagai cookie berumur
        // panjang, TERPISAH dari session login, supaya navigasi biasa
        // (klik back, tab baru, dsb) tidak ikut mengubahnya.
        $deviceToken = $request->cookie('qz_device_id') ?: Str::random(48);
        Cookie::queue(Cookie::make(
            'qz_device_id',
            $deviceToken,
            60 * 24 * 30 // 30 hari, jauh melebihi durasi kuis manapun
        ));

        // Pemeriksaan dan pembuatan attempt dalam satu transaksi dengan baris
        // mahasiswa dikunci, supaya klik ganda tidak menghasilkan 2 attempt.
        [$outcome, $attempt] = DB::transaction(function () use ($quiz, $user, $questionIds, $request, $deviceToken) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $existing = Attempt::where('quiz_id', $quiz->id)->where('user_id', $user->id)->orderBy('id')->get();

            // Attempt yang masih berjalan selalu dilanjutkan. Kalau waktunya
            // sudah habis, halaman kerjakan yang akan menutup dan menilainya.
            if ($ongoing = $existing->firstWhere('status', 'in_progress')) {
                return ['resume', $ongoing];
            }

            if ($existing->count() >= Quiz::MAX_ATTEMPTS) {
                return ['limit', null];
            }

            if (!$quiz->isOpenNow()) {
                return ['closed', null];
            }

            $attempt = Attempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'question_order' => $questionIds,
                'started_at' => now(),
                'deadline_at' => now()->addMinutes($quiz->duration_minutes),
                'status' => 'in_progress',
                'browser_session_id' => $request->session()->getId(),
                'device_token' => $deviceToken,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            // ---- Perbaikan N+1: ambil SEMUA tipe soal & SEMUA opsi dalam
            // 2 query saja (bukan 2 query PER SOAL seperti sebelumnya) ----
            $questionsById = DB::table('questions')
                ->whereIn('id', $questionIds)
                ->get(['id', 'type'])
                ->keyBy('id');

            $optionsByQuestion = DB::table('options')
                ->whereIn('question_id', $questionIds)
                ->get(['id', 'question_id'])
                ->groupBy('question_id');

            $now = now();
            $rows = [];

            foreach ($questionIds as $qid) {
                $question = $questionsById->get($qid);

                $optionIds = [];
                if ($question->type === 'pilihan_ganda') {
                    $optionIds = $optionsByQuestion->get($qid, collect())->pluck('id')->all();
                    if ($quiz->shuffle_options) {
                        shuffle($optionIds);
                    }
                }

                $rows[] = [
                    'attempt_id' => $attempt->id,
                    'question_id' => $qid,
                    'option_order' => json_encode($optionIds),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Satu query INSERT untuk semua jawaban sekaligus, bukan satu
            // per satu lewat ->create() di dalam loop.
            DB::table('attempt_answers')->insert($rows);

            return ['created', $attempt];
        });

        if ($outcome === 'limit') {
            return $this->backWithError($request, $quiz, 'Anda sudah mengerjakan kuis ini. Setiap kuis hanya bisa dikerjakan satu kali.');
        }
        if ($outcome === 'closed') {
            return $this->backWithError($request, $quiz, 'Kuis belum dibuka atau sudah ditutup.');
        }

        return redirect()->route('mahasiswa.quizzes.take', $attempt);
    }

    public function take(Request $request, Attempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);

        if ($mismatch = $this->deviceMismatchResponse($request, $attempt)) {
            return $mismatch;
        }

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('mahasiswa.quizzes.result', $attempt);
        }

        if ($attempt->isExpired()) {
            $this->finalizeAttempt($attempt);
            return redirect()->route('mahasiswa.quizzes.result', $attempt)
                ->with('status', 'Waktu pengerjaan habis, jawaban Anda telah disimpan otomatis.');
        }

        $attempt->load('answers.question.options');

        $questions = collect($attempt->question_order)->map(function ($qid) use ($attempt) {
            $answer = $attempt->answers->firstWhere('question_id', $qid);
            $question = $answer->question;

            $orderedOptions = collect();
            if ($question->type === 'pilihan_ganda') {
                $optionsById = $question->options->keyBy('id');
                $orderedOptions = collect($answer->option_order)->map(fn ($oid) => $optionsById->get($oid));
            }

            return [
                'question' => $question,
                'answer_id' => $answer->id,
                'options' => $orderedOptions,
                'selected_option_id' => $answer->selected_option_id,
                'answer_text' => $answer->answer_text,
            ];
        });

        return view('mahasiswa.quizzes.take', [
            'attempt' => $attempt,
            'questions' => $questions,
        ]);
    }

    /**
     * Auto-save satu jawaban. Menangani 3 tipe: pilihan_ganda (option_id),
     * esai & isian_singkat (answer_text) -- reuse endpoint & pola AJAX yang
     * sama, cuma payload-nya beda tergantung tipe soal.
     */
    public function saveAnswer(Request $request, Attempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);

        if ($this->deviceMismatchResponse($request, $attempt)) {
            return response()->json(['status' => 'device_mismatch'], 409);
        }

        abort_unless($attempt->status === 'in_progress', 409, 'Attempt sudah tidak aktif.');
        abort_if($attempt->isExpired(), 409, 'Waktu sudah habis.');

        $data = $request->validate([
            'answer_id' => ['required', 'integer', 'exists:attempt_answers,id'],
            'option_id' => ['nullable', 'integer', 'exists:options,id'],
            'answer_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $answer = $attempt->answers()->with('question')->findOrFail($data['answer_id']);

        if ($answer->question->type === 'pilihan_ganda') {
            abort_unless(!empty($data['option_id']) && in_array($data['option_id'], $answer->option_order), 422);
            $answer->update(['selected_option_id' => $data['option_id']]);
        } else {
            $answer->update(['answer_text' => $data['answer_text'] ?? '']);
        }

        return response()->json(['status' => 'saved']);
    }

    public function reportTabSwitch(Request $request, Attempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);

        if ($this->deviceMismatchResponse($request, $attempt)) {
            return response()->json(['status' => 'device_mismatch'], 409);
        }

        if ($attempt->status === 'in_progress') {
            $attempt->increment('tab_switch_count');
        }

        return response()->json(['status' => 'noted', 'count' => $attempt->tab_switch_count]);
    }

    public function submit(Request $request, Attempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);

        if ($mismatch = $this->deviceMismatchResponse($request, $attempt)) {
            return $mismatch;
        }

        abort_unless($attempt->status === 'in_progress', 409, 'Attempt sudah tidak aktif.');

        $this->finalizeAttempt($attempt);

        $message = $attempt->fresh()->needs_grading
            ? 'Jawaban berhasil disubmit. Kuis ini punya soal esai, nilai akhir akan muncul setelah dosen selesai menilai.'
            : 'Jawaban berhasil disubmit.';

        return redirect()->route('mahasiswa.quizzes.result', $attempt)->with('status', $message);
    }

    public function result(Request $request, Attempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);
        abort_if($attempt->status === 'in_progress', 403, 'Kuis belum disubmit.');

        $attempt->load('quiz', 'answers');

        return view('mahasiswa.quizzes.result', compact('attempt'));
    }

    public function deviceMismatch(Request $request, Attempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);
        return view('mahasiswa.quizzes.device-mismatch', compact('attempt'));
    }

    private function deviceMismatchResponse(Request $request, Attempt $attempt)
    {
        if (!$attempt->isFromDifferentDevice($request->cookie('qz_device_id'))) {
            return null;
        }
        if ($request->expectsJson()) {
            return response()->json(['status' => 'device_mismatch'], 409);
        }
        return redirect()->route('mahasiswa.quizzes.deviceMismatch', $attempt);
    }

    /**
     * Penilaian dipindah ke App\Services\AttemptFinalizer supaya dipakai
     * bersama oleh command attempts:finalize-expired. Rumus tidak berubah.
     */
    private function finalizeAttempt(Attempt $attempt): void
    {
        app(AttemptFinalizer::class)->finalize($attempt);
    }

    /**
     * Penolakan saat mulai kuis ditampilkan sebagai pesan di halaman asal,
     * bukan halaman error, supaya mahasiswa tahu harus berbuat apa.
     */
    private function backWithError(Request $request, Quiz $quiz, string $message)
    {
        $target = $request->filled('code') && $quiz->code
            ? route('mahasiswa.quizzes.found', $quiz->code)
            : route('mahasiswa.quizzes.index');

        return redirect($target)->withErrors(['quiz' => $message]);
    }

    private function authorizeOwner(Request $request, Attempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
