<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dosen\Concerns\HandlesQuestionImage;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualQuestionController extends Controller
{
    use HandlesQuestionImage;

    public function create(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        return view('dosen.questions.create', compact('quiz'));
    }

    public function store(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $data = $request->validate([
            'type' => ['required', 'in:pilihan_ganda,esai,isian_singkat'],
            'question_text' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'options' => ['required_if:type,pilihan_ganda', 'array', 'min:2'],
            'options.*.text' => ['required_with:options', 'string'],
            'correct_index' => ['required_if:type,pilihan_ganda', 'integer'],
            'correct_text_answer' => ['required_if:type,isian_singkat', 'nullable', 'string', 'max:255'],
        ]);

        $imagePath = $this->storeQuestionImage($request->file('image'));

        DB::transaction(function () use ($quiz, $data, $imagePath) {
            $order = $quiz->questions()->max('order') + 1;

            $question = $quiz->questions()->create([
                'question_text' => $data['question_text'],
                'type' => $data['type'],
                'correct_text_answer' => $data['type'] === 'isian_singkat' ? $data['correct_text_answer'] : null,
                'image_path' => $imagePath,
                'order' => $order,
            ]);

            if ($data['type'] === 'pilihan_ganda') {
                foreach ($data['options'] as $i => $opt) {
                    $question->options()->create([
                        'option_text' => $opt['text'],
                        'is_correct' => (int) $data['correct_index'] === $i,
                        'order' => $i,
                    ]);
                }
            }
        });

        return redirect()
            ->route('dosen.quizzes.edit', $quiz)
            ->with('status', 'Soal berhasil ditambahkan.');
    }

    /**
     * Edit soal PILIHAN GANDA yang sudah ada di suatu kuis. Sengaja dibatasi
     * hanya untuk tipe pilihan_ganda dulu (esai/isian belum ada form edit-nya).
     */
    public function edit(Request $request, Question $question)
    {
        abort_unless($question->quiz->dosen_id === $request->user()->id, 403);
        abort_unless($question->type === 'pilihan_ganda', 422, 'Edit manual saat ini hanya tersedia untuk soal pilihan ganda.');

        $question->load('options');

        return view('dosen.questions.edit', compact('question'));
    }

    public function update(Request $request, Question $question)
    {
        abort_unless($question->quiz->dosen_id === $request->user()->id, 403);
        abort_unless($question->type === 'pilihan_ganda', 422, 'Edit manual saat ini hanya tersedia untuk soal pilihan ganda.');

        $data = $request->validate([
            'question_text' => ['required', 'string'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.text' => ['required', 'string'],
            'correct_index' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($question, $data) {
            $question->update(['question_text' => $data['question_text']]);

            $question->options()->delete();

            foreach ($data['options'] as $i => $opt) {
                $question->options()->create([
                    'option_text' => $opt['text'],
                    'is_correct' => (int) $data['correct_index'] === $i,
                    'order' => $i,
                ]);
            }
        });

        return redirect()
            ->route('dosen.quizzes.edit', $question->quiz)
            ->with('status', 'Soal berhasil diperbarui.');
    }

    public function destroy(Request $request, Question $question)
    {
        abort_unless($question->quiz->dosen_id === $request->user()->id, 403);

        $quiz = $question->quiz;
        $question->delete();

        return redirect()
            ->route('dosen.quizzes.edit', $quiz)
            ->with('status', 'Soal dihapus dari kuis.');
    }
}
