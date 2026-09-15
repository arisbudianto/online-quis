<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Notifications\QuizPublishedNotification;
use App\Notifications\QuizPublishedEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $quizzes = Quiz::where('dosen_id', $request->user()->id)
            ->with('course')
            ->withCount([
                'questions',
                'attempts',
                'attempts as submitted_count' => fn ($q) => $q->where('status', 'submitted'),
            ])
            ->latest()
            ->paginate(10);

        return view('dosen.quizzes.index', compact('quizzes'));
    }

    public function create(Request $request)
    {
        $courses = Course::where('dosen_id', $request->user()->id)->orderBy('name')->get();

        return view('dosen.quizzes.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after:start_time'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_options' => ['nullable', 'boolean'],
            'questions_to_show' => ['nullable', 'integer', 'min:1'],
        ]);

        if (!empty($data['course_id'])) {
            $ownsCourse = Course::where('id', $data['course_id'])
                ->where('dosen_id', $request->user()->id)
                ->exists();
            abort_unless($ownsCourse, 403);
        }

        $data['dosen_id'] = $request->user()->id;
        $data['shuffle_questions'] = $request->boolean('shuffle_questions', true);
        $data['shuffle_options'] = $request->boolean('shuffle_options', true);

        $quiz = Quiz::create($data);

        return redirect()
            ->route('dosen.quizzes.edit', $quiz)
            ->with('status', 'Kuis berhasil dibuat. Silakan unggah soal (.xlsx).');
    }

    public function edit(Request $request, Quiz $quiz)
    {
        $this->authorizeOwner($request, $quiz);
        $quiz->load('questions.options');
        $courses = Course::where('dosen_id', $request->user()->id)->orderBy('name')->get();

        return view('dosen.quizzes.edit', compact('quiz', 'courses'));
    }

    public function update(Request $request, Quiz $quiz)
    {
        $this->authorizeOwner($request, $quiz);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after:start_time'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'questions_to_show' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['nullable', 'boolean'],
            'target_kelas' => ['nullable', 'array'],
            'target_kelas.*' => ['in:A,B,C'],
            'notify_students' => ['nullable', 'boolean'],
            'notify_email' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['course_id'])) {
            $ownsCourse = Course::where('id', $data['course_id'])
                ->where('dosen_id', $request->user()->id)
                ->exists();
            abort_unless($ownsCourse, 403);
        }

        $data['shuffle_questions'] = $request->boolean('shuffle_questions', true);
        $data['shuffle_options'] = $request->boolean('shuffle_options', true);
        $data['is_published'] = $request->boolean('is_published', false);
        $data['target_kelas'] = !empty($data['target_kelas']) ? $data['target_kelas'] : null;

        $notifyStudents = $request->boolean('notify_students', false);
        $notifyEmail = $request->boolean('notify_email', false);

        unset($data['notify_students'], $data['notify_email']);

        $quiz->update($data);

        $status = 'Perubahan kuis disimpan.';

        // Kirim notifikasi HANYA kalau dosen mencentang opsi itu secara sadar
        // (bukan otomatis tiap kali is_published true, supaya tidak spam
        // kalau dosen edit-edit pengaturan kuis yang sudah lama terbit).
        if ($notifyStudents && $data['is_published']) {
            $students = $quiz->fresh()->targetStudents();

            if ($students->isNotEmpty()) {
                // Notifikasi database (lonceng navbar) -- SYNCHRONOUS, langsung
                // muncul, murni INSERT ringan walau ke ratusan mahasiswa.
                Notification::send($students, new QuizPublishedNotification($quiz));

                $status .= ' Notifikasi terkirim ke ' . $students->count() . ' mahasiswa (lonceng aplikasi).';

                // Email -- QUEUED (App\Notifications\QuizPublishedEmailNotification
                // implements ShouldQueue), diproses bertahap oleh cron job, TIDAK
                // memblokir request ini walau audiencenya besar.
                if ($notifyEmail) {
                    Notification::send($students, new QuizPublishedEmailNotification($quiz));
                    $status .= ' Email diproses di background, cek beberapa menit lagi kalau belum masuk.';
                }
            } else {
                $status .= ' Tidak ada mahasiswa yang cocok dengan target kelas untuk dikirimi notifikasi.';
            }
        }

        return back()->with('status', $status);
    }

    public function destroy(Request $request, Quiz $quiz)
    {
        $this->authorizeOwner($request, $quiz);
        $quiz->delete();

        return redirect()->route('dosen.quizzes.index')->with('status', 'Kuis dihapus.');
    }

    private function authorizeOwner(Request $request, Quiz $quiz): void
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);
    }
}
