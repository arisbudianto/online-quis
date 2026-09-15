<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\BankQuestion;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizBankController extends Controller
{
    public function picker(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);
        abort_unless($quiz->course_id, 422, 'Kuis ini belum dikaitkan ke mata kuliah. Kaitkan dulu di Pengaturan Kuis untuk memakai Bank Soal.');

        $bankQuestions = BankQuestion::where('course_id', $quiz->course_id)
            ->with('options')
            ->latest()
            ->get();

        return view('dosen.quizzes.bank-picker', compact('quiz', 'bankQuestions'));
    }

    public function add(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $data = $request->validate([
            'bank_question_ids' => ['nullable', 'array'],
            'bank_question_ids.*' => ['integer', 'exists:bank_questions,id'],
            'random_count' => ['nullable', 'integer', 'min:1'],
        ]);

        $idsToAdd = collect($data['bank_question_ids'] ?? []);

        if (!empty($data['random_count'])) {
            $alreadyChosen = $idsToAdd->all();
            $randomIds = BankQuestion::where('course_id', $quiz->course_id)
                ->whereNotIn('id', $alreadyChosen)
                ->inRandomOrder()
                ->limit($data['random_count'])
                ->pluck('id');
            $idsToAdd = $idsToAdd->merge($randomIds);
        }

        $idsToAdd = $idsToAdd->unique()->values();

        if ($idsToAdd->isEmpty()) {
            return back()->withErrors(['bank_question_ids' => 'Pilih minimal satu soal, atau isi jumlah soal acak.']);
        }

        $bankQuestions = BankQuestion::where('course_id', $quiz->course_id)
            ->whereIn('id', $idsToAdd)
            ->with('options')
            ->get();

        $startOrder = $quiz->questions()->max('order') + 1;

        DB::transaction(function () use ($quiz, $bankQuestions, $startOrder) {
            foreach ($bankQuestions as $i => $bq) {
                $question = $quiz->questions()->create([
                    'question_text' => $bq->question_text,
                    'type' => $bq->type,
                    'correct_text_answer' => $bq->correct_text_answer,
                    'image_path' => $bq->image_path,
                    'order' => $startOrder + $i,
                ]);

                foreach ($bq->options as $opt) {
                    $question->options()->create([
                        'option_text' => $opt->option_text,
                        'is_correct' => $opt->is_correct,
                        'order' => $opt->order,
                    ]);
                }
            }
        });

        return redirect()
            ->route('dosen.quizzes.edit', $quiz)
            ->with('status', $bankQuestions->count() . ' soal dari bank berhasil ditambahkan ke kuis ini.');
    }
}
