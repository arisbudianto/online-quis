<?php

namespace App\Http\Controllers\Dosen;

use App\Models\User;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
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
        // Filter: '' = semua, 'none' = belum diisi, selain itu ID.
        $periodFilter = (string) $request->query('periode', '');
        $courseFilter = (string) $request->query('mk', '');

        $quizzes = Quiz::where('dosen_id', $request->user()->id)
            ->with(['course', 'academicPeriod'])
            ->withCount([
                'questions',
                'attempts',
                'attempts as submitted_count' => fn ($q) => $q->where('status', 'submitted'),
            ])
            ->when($periodFilter === 'none', fn ($q) => $q->whereNull('academic_period_id'))
            ->when(ctype_digit($periodFilter), fn ($q) => $q->where('academic_period_id', (int) $periodFilter))
            ->when($courseFilter === 'none', fn ($q) => $q->whereNull('course_id'))
            ->when(ctype_digit($courseFilter), fn ($q) => $q->where('course_id', (int) $courseFilter))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('dosen.quizzes.index', [
            'quizzes' => $quizzes,
            'periods' => AcademicPeriod::latestFirst()->get(),
            'courses' => Course::where('dosen_id', $request->user()->id)->orderBy('name')->get(),
            'periodFilter' => $periodFilter,
            'courseFilter' => $courseFilter,
        ]);
    }

    public function create(Request $request)
    {
        $courses = Course::where('dosen_id', $request->user()->id)->orderBy('name')->get();
        $periods = AcademicPeriod::latestFirst()->get();
        $defaultPeriodId = AcademicPeriod::active()?->id;

        return view('dosen.quizzes.create', compact('courses', 'periods', 'defaultPeriodId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'academic_period_id' => ['nullable', 'exists:academic_periods,id'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after:start_time'],
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
        $data['max_attempts'] = Quiz::MAX_ATTEMPTS;
        // Field tidak dikirim sama sekali => pakai periode aktif.
        // Dikirim kosong => dosen sengaja memilih "tanpa periode".
        if (!$request->has('academic_period_id')) {
            $data['academic_period_id'] = AcademicPeriod::active()?->id;
        }
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
        $periods = AcademicPeriod::latestFirst()->get();

        return view('dosen.quizzes.edit', compact('quiz', 'courses', 'periods'));
    }

    public function update(Request $request, Quiz $quiz)
    {
        $this->authorizeOwner($request, $quiz);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'academic_period_id' => ['nullable', 'exists:academic_periods,id'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after:start_time'],
            'questions_to_show' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'target_kelas' => ['nullable', 'array'],
            'target_kelas.*' => [Rule::in(User::KELAS)],
            'notify_students' => ['nullable', 'boolean'],
            'notify_email' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['course_id'])) {
            $ownsCourse = Course::where('id', $data['course_id'])
                ->where('dosen_id', $request->user()->id)
                ->exists();
            abort_unless($ownsCourse, 403);
        }

        $data['max_attempts'] = Quiz::MAX_ATTEMPTS;
        $data['shuffle_questions'] = $request->boolean('shuffle_questions', true);
        $data['shuffle_options'] = $request->boolean('shuffle_options', true);
        $data['is_published'] = $request->boolean('is_published', false);
        // PENTING: default HARUS false, bukan true. Kalau default true,
        // checkbox yang di-uncheck (tidak terkirim oleh browser) akan selalu
        // dibaca sebagai true juga -- membuat toggle privat tidak pernah
        // benar-benar bisa dimatikan. (Ini bug yang sempat ada sebelumnya.)
        $data['is_public'] = $request->boolean('is_public', false);
        $data['target_kelas'] = !empty($data['target_kelas']) ? $data['target_kelas'] : null;

        // Kuis tanpa soal tidak boleh terbit: mahasiswa akan menemukan kuis
        // lewat kode tetapi tidak bisa memulainya (kejadian kuis 10, Okt 2026).
        if ($data['is_published'] && !$quiz->questions()->exists()) {
            return back()->withInput()->withErrors([
                'is_published' => 'Kuis belum punya soal. Tambahkan soal dulu sebelum menerbitkan.',
            ]);
        }

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
