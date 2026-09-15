<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizAnalyticsController extends Controller
{
    public function show(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $totalSubmitted = $quiz->attempts()->where('status', 'submitted')->count();

        // ---- 1a. Soal tersulit: HANYA pilihan_ganda & isian_singkat --
        // soal esai tidak punya konsep "benar/salah" biner, jadi tidak masuk
        // ranking ini (ditampilkan terpisah di bawah sebagai rata-rata poin).
        $questionStats = DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->where('attempts.quiz_id', $quiz->id)
            ->where('attempts.status', 'submitted')
            ->whereIn('questions.type', ['pilihan_ganda', 'isian_singkat'])
            ->select(
                'attempt_answers.question_id',
                DB::raw('COUNT(*) as total_answered'),
                DB::raw('SUM(CASE WHEN attempt_answers.is_correct = 1 THEN 1 ELSE 0 END) as total_correct')
            )
            ->groupBy('attempt_answers.question_id')
            ->get()
            ->keyBy('question_id');

        $gradableQuestions = $quiz->questions()
            ->whereIn('type', ['pilihan_ganda', 'isian_singkat'])
            ->orderBy('order')
            ->get(['id', 'question_text']);

        $questionAnalytics = $gradableQuestions->map(function ($q) use ($questionStats) {
            $stat = $questionStats->get($q->id);
            $answered = $stat->total_answered ?? 0;
            $correct = $stat->total_correct ?? 0;
            $percentage = $answered > 0 ? round(($correct / $answered) * 100, 1) : null;

            return [
                'question_text' => $q->question_text,
                'total_answered' => $answered,
                'total_correct' => $correct,
                'percentage' => $percentage,
            ];
        })->sortBy(fn ($item) => $item['percentage'] ?? 999)->values();

        // ---- Rata-rata poin soal esai (terpisah, karena skalanya beda: poin manual dosen) ----
        $essayStats = DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->where('attempts.quiz_id', $quiz->id)
            ->where('attempts.status', 'submitted')
            ->where('questions.type', 'esai')
            ->whereNotNull('attempt_answers.points') // hanya yang sudah dinilai dosen
            ->select(
                'attempt_answers.question_id',
                'questions.question_text',
                DB::raw('AVG(attempt_answers.points) as avg_points'),
                DB::raw('COUNT(*) as total_graded')
            )
            ->groupBy('attempt_answers.question_id', 'questions.question_text')
            ->get();

        // ---- 1b. Distribusi skor: hanya attempt yang skornya sudah final (tidak needs_grading) ----
        $scores = $quiz->attempts()
            ->where('status', 'submitted')
            ->where('needs_grading', false)
            ->pluck('score');

        $buckets = ['0-20' => 0, '21-40' => 0, '41-60' => 0, '61-80' => 0, '81-100' => 0];
        foreach ($scores as $score) {
            $score = (float) $score;
            if ($score <= 20) $buckets['0-20']++;
            elseif ($score <= 40) $buckets['21-40']++;
            elseif ($score <= 60) $buckets['41-60']++;
            elseif ($score <= 80) $buckets['61-80']++;
            else $buckets['81-100']++;
        }

        $pendingGradingCount = $quiz->attempts()->where('status', 'submitted')->where('needs_grading', true)->count();

        // ---- 1c. Rata-rata skor per kelas: hanya yang skornya final ----
        $classStats = DB::table('attempts')
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->where('attempts.quiz_id', $quiz->id)
            ->where('attempts.status', 'submitted')
            ->where('attempts.needs_grading', false)
            ->select(
                'users.kelas',
                DB::raw('AVG(attempts.score) as avg_score'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('users.kelas')
            ->orderBy('users.kelas')
            ->get();

        $showClassComparison = $classStats->pluck('kelas')->filter()->unique()->count() > 1;

        return view('dosen.quizzes.analytics', [
            'quiz' => $quiz,
            'totalSubmitted' => $totalSubmitted,
            'questionAnalytics' => $questionAnalytics,
            'essayStats' => $essayStats,
            'buckets' => $buckets,
            'classStats' => $classStats,
            'showClassComparison' => $showClassComparison,
            'pendingGradingCount' => $pendingGradingCount,
        ]);
    }
}
