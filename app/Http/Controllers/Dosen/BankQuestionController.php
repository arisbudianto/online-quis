<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dosen\Concerns\HandlesQuestionImage;
use App\Imports\QuestionsImport;
use App\Models\BankQuestion;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class BankQuestionController extends Controller
{
    use HandlesQuestionImage;

    public function index(Request $request, Course $course)
    {
        abort_unless($course->dosen_id === $request->user()->id, 403);

        $bankQuestions = BankQuestion::where('course_id', $course->id)
            ->with('options')
            ->latest()
            ->paginate(20);

        return view('dosen.bank.index', compact('course', 'bankQuestions'));
    }

    public function import(Request $request, Course $course)
    {
        abort_unless($course->dosen_id === $request->user()->id, 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $import = new QuestionsImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal membaca file xlsx: ' . $e->getMessage()]);
        }

        if (empty($import->parsed)) {
            $message = 'Tidak ada soal valid yang terdeteksi. Pastikan format sesuai template.';
            if (!empty($import->skipped)) {
                $message .= ' Detail: ' . implode(' | ', array_slice($import->skipped, 0, 5));
            }
            return back()->withErrors(['file' => $message]);
        }

        DB::transaction(function () use ($course, $request, $import) {
            foreach ($import->parsed as $item) {
                // Import xlsx SELALU pilihan_ganda (kolom xlsx memang cuma untuk itu).
                $bankQuestion = BankQuestion::create([
                    'course_id' => $course->id,
                    'dosen_id' => $request->user()->id,
                    'question_text' => $item['question'],
                    'type' => 'pilihan_ganda',
                ]);

                foreach ($item['options'] as $optIndex => $opt) {
                    $bankQuestion->options()->create([
                        'option_text' => $opt['text'],
                        'is_correct' => $opt['is_correct'],
                        'order' => $optIndex,
                    ]);
                }
            }
        });

        $status = count($import->parsed) . ' soal berhasil ditambahkan ke bank soal.';
        if (!empty($import->skipped)) {
            $status .= ' ' . count($import->skipped) . ' baris dilewati: ' . implode(' | ', array_slice($import->skipped, 0, 5));
        }

        return back()->with('status', $status);
    }

    public function createManual(Request $request, Course $course)
    {
        abort_unless($course->dosen_id === $request->user()->id, 403);
        return view('dosen.bank.create', compact('course'));
    }

    public function storeManual(Request $request, Course $course)
    {
        abort_unless($course->dosen_id === $request->user()->id, 403);

        $data = $request->validate([
            'type' => ['required', 'in:pilihan_ganda,esai,isian_singkat'],
            'question_text' => ['required', 'string'],
            'tag' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'options' => ['required_if:type,pilihan_ganda', 'array', 'min:2'],
            'options.*.text' => ['required_with:options', 'string'],
            'correct_index' => ['required_if:type,pilihan_ganda', 'integer'],
            'correct_text_answer' => ['required_if:type,isian_singkat', 'nullable', 'string', 'max:255'],
        ]);

        $imagePath = $this->storeQuestionImage($request->file('image'));

        DB::transaction(function () use ($course, $request, $data, $imagePath) {
            $bankQuestion = BankQuestion::create([
                'course_id' => $course->id,
                'dosen_id' => $request->user()->id,
                'question_text' => $data['question_text'],
                'tag' => $data['tag'] ?? null,
                'type' => $data['type'],
                'correct_text_answer' => $data['type'] === 'isian_singkat' ? $data['correct_text_answer'] : null,
                'image_path' => $imagePath,
            ]);

            if ($data['type'] === 'pilihan_ganda') {
                foreach ($data['options'] as $i => $opt) {
                    $bankQuestion->options()->create([
                        'option_text' => $opt['text'],
                        'is_correct' => (int) $data['correct_index'] === $i,
                        'order' => $i,
                    ]);
                }
            }
        });

        return redirect()->route('dosen.bank.index', $course)->with('status', 'Soal berhasil ditambahkan ke bank.');
    }

    public function edit(Request $request, BankQuestion $bankQuestion)
    {
        abort_unless($bankQuestion->dosen_id === $request->user()->id, 403);
        $bankQuestion->load('options');
        return view('dosen.bank.edit', compact('bankQuestion'));
    }

    public function update(Request $request, BankQuestion $bankQuestion)
    {
        abort_unless($bankQuestion->dosen_id === $request->user()->id, 403);

        // Catatan: edit manual saat ini tetap fokus untuk soal pilihan_ganda
        // (form edit lama). Untuk ganti tipe soal, hapus lalu buat ulang lewat
        // form tambah manual -- lebih aman daripada migrasi data opsi di tengah edit.
        $data = $request->validate([
            'question_text' => ['required', 'string'],
            'tag' => ['nullable', 'string', 'max:255'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.text' => ['required', 'string'],
            'correct_index' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($bankQuestion, $data) {
            $bankQuestion->update([
                'question_text' => $data['question_text'],
                'tag' => $data['tag'] ?? null,
            ]);

            $bankQuestion->options()->delete();

            foreach ($data['options'] as $i => $opt) {
                $bankQuestion->options()->create([
                    'option_text' => $opt['text'],
                    'is_correct' => (int) $data['correct_index'] === $i,
                    'order' => $i,
                ]);
            }
        });

        return redirect()->route('dosen.bank.index', $bankQuestion->course_id)->with('status', 'Soal berhasil diperbarui.');
    }

    public function destroy(Request $request, BankQuestion $bankQuestion)
    {
        abort_unless($bankQuestion->dosen_id === $request->user()->id, 403);
        $courseId = $bankQuestion->course_id;
        $bankQuestion->delete();
        return redirect()->route('dosen.bank.index', $courseId)->with('status', 'Soal dihapus dari bank.');
    }
}
