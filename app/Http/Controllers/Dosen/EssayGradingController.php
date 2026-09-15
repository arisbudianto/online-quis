<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EssayGradingController extends Controller
{
    /**
     * Daftar attempt yang punya jawaban esai untuk kuis ini, dengan status
     * penilaian (sudah/belum) supaya dosen tahu mana yang perlu dikerjakan.
     */
    public function index(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $hasEssay = $quiz->questions()->where('type', 'esai')->exists();
        abort_unless($hasEssay, 404, 'Kuis ini tidak punya soal esai.');

        $attempts = $quiz->attempts()
            ->where('status', 'submitted')
            ->with('user')
            ->orderByDesc('needs_grading')
            ->orderBy('submitted_at')
            ->get();

        return view('dosen.grading.index', compact('quiz', 'attempts'));
    }

    /**
     * Halaman nilai satu attempt: tampilkan semua jawaban esai mahasiswa itu,
     * dosen isi poin 0-100 per soal esai.
     */
    public function edit(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->quiz->dosen_id === $request->user()->id, 403);

        $attempt->load(['user', 'answers' => function ($q) {
            $q->whereHas('question', fn ($qq) => $qq->where('type', 'esai'))->with('question');
        }]);

        return view('dosen.grading.edit', compact('attempt'));
    }

    public function update(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->quiz->dosen_id === $request->user()->id, 403);

        $data = $request->validate([
            'points' => ['required', 'array'],
            'points.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($attempt, $data) {
            foreach ($data['points'] as $answerId => $point) {
                if ($point === null || $point === '') {
                    continue;
                }
                $attempt->answers()->where('id', $answerId)->update(['points' => $point]);
            }

            $attempt->recalculateScoreIfReady();
        });

        $fresh = $attempt->fresh();
        $status = $fresh->needs_grading
            ? 'Nilai esai tersimpan. Masih ada soal esai lain di attempt ini yang belum dinilai.'
            : 'Semua esai sudah dinilai, skor akhir attempt ini final: ' . $fresh->score . '.';

        return redirect()
            ->route('dosen.grading.index', $attempt->quiz)
            ->with('status', $status);
    }
}
