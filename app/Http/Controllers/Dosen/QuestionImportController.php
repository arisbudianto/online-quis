<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Imports\QuestionsImport;
use App\Models\Quiz;
use App\Services\DocxQuestionParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class QuestionImportController extends Controller
{
    public function store(Request $request, Quiz $quiz, DocxQuestionParser $docxParser)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,docx', 'max:10240'], // maks 10MB
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->importFromXlsx($request, $quiz);
        }

        return $this->importFromDocx($request, $quiz, $docxParser);
    }

    private function importFromXlsx(Request $request, Quiz $quiz)
    {
        $import = new QuestionsImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            return back()->withErrors([
                'file' => 'Gagal membaca file xlsx: ' . $e->getMessage(),
            ]);
        }

        if (empty($import->parsed)) {
            $message = 'Tidak ada soal valid yang terdeteksi. Pastikan format sesuai template.';
            if (!empty($import->skipped)) {
                $message .= ' Detail: ' . implode(' | ', array_slice($import->skipped, 0, 5));
            }
            return back()->withErrors(['file' => $message]);
        }

        $this->saveQuestions($quiz, $import->parsed);

        $status = count($import->parsed) . ' soal berhasil diimpor.';
        if (!empty($import->skipped)) {
            $status .= ' ' . count($import->skipped) . ' baris dilewati karena tidak lengkap: '
                . implode(' | ', array_slice($import->skipped, 0, 5))
                . (count($import->skipped) > 5 ? ' (dan lainnya)' : '');
        }

        return back()->with('status', $status);
    }

    private function importFromDocx(Request $request, Quiz $quiz, DocxQuestionParser $parser)
    {
        $uploadedPath = $request->file('file')->getRealPath();

        try {
            $parsedQuestions = $parser->parse($uploadedPath);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'file' => 'Gagal membaca file docx: ' . $e->getMessage(),
            ]);
        }

        if (empty($parsedQuestions)) {
            return back()->withErrors([
                'file' => 'Tidak ada soal valid yang terdeteksi dari docx. Sebaiknya gunakan template .xlsx yang lebih akurat.',
            ]);
        }

        $this->saveQuestions($quiz, $parsedQuestions);

        return back()->with('status', count($parsedQuestions) . ' soal berhasil diimpor dari docx.');
    }

    private function saveQuestions(Quiz $quiz, array $parsedQuestions): void
    {
        DB::transaction(function () use ($quiz, $parsedQuestions) {
            foreach ($parsedQuestions as $index => $item) {
                $question = $quiz->questions()->create([
                    'question_text' => $item['question'],
                    'order' => $index,
                ]);

                foreach ($item['options'] as $optIndex => $opt) {
                    $question->options()->create([
                        'option_text' => $opt['text'],
                        'is_correct' => $opt['is_correct'],
                        'order' => $optIndex,
                    ]);
                }
            }
        });
    }
}
