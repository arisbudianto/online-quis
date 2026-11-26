<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Dosen\QuestionImportController;
use App\Http\Controllers\Dosen\QuizController as DosenQuizController;
use App\Http\Controllers\Dosen\ScoreController;
use App\Http\Controllers\Dosen\CourseController;
use App\Http\Controllers\Dosen\BankQuestionController;
use App\Http\Controllers\Dosen\QuizBankController;
use App\Http\Controllers\Dosen\QuizQrCodeController;
use App\Http\Controllers\Dosen\StudentImportController;
use App\Http\Controllers\Dosen\StudentController as DosenStudentController;
use App\Http\Controllers\Dosen\QuizAnalyticsController;
use App\Http\Controllers\Dosen\ManualQuestionController;
use App\Http\Controllers\Dosen\EssayGradingController;
use App\Http\Controllers\Dosen\SemesterRecapController;
use App\Http\Controllers\Dosen\CourseEnrollmentController;
use App\Http\Controllers\Dosen\TeacherClassController;
use App\Http\Controllers\Dosen\LiveSessionController;
use App\Http\Controllers\Live\PlayController as LivePlayController;
use App\Http\Controllers\Mahasiswa\HistoryController;
use App\Http\Controllers\Mahasiswa\QuizController as MahasiswaQuizController;
use App\Http\Controllers\Mahasiswa\QuizJoinController;
use App\Http\Controllers\Mahasiswa\NotificationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LecturerController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\AcademicPeriodController;
use App\Http\Controllers\Admin\GradeScaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Catatan: route auth (login/register/logout) disediakan oleh
| Laravel Breeze -> routes/auth.php (di-include otomatis lewat php artisan
| breeze:install). Jangan lupa jalankan itu setelah composer install.
*/

Route::get('/', function () {
        return view('welcome');
    })->name('welcome');

// ================= KUIS INTERAKTIF: PESERTA =================
// Tanpa login: peserta scan QR, isi nama panggilan, lalu main. Tidak ada
// throttle per IP karena satu kelas biasanya keluar lewat satu IP kampus.
Route::prefix('main')->name('live.')->group(function () {
    Route::get('/', [LivePlayController::class, 'enter'])->name('enter');
    Route::post('/', [LivePlayController::class, 'enterSubmit'])->name('enter.submit');
    Route::get('jam', [LivePlayController::class, 'clock'])->name('clock');
    Route::get('{code}', [LivePlayController::class, 'join'])->name('join');
    Route::post('{code}', [LivePlayController::class, 'joinSubmit'])->name('join.submit');
    Route::get('{code}/main', [LivePlayController::class, 'play'])->name('play');
    Route::post('{code}/jawab', [LivePlayController::class, 'answer'])->name('answer');
    Route::get('{code}/saya', [LivePlayController::class, 'me'])->name('me');
    Route::get('{code}/soal', [LivePlayController::class, 'challengeQuestion'])->name('challenge.question');
    Route::post('{code}/tantangan/jawab', [LivePlayController::class, 'challengeAnswer'])->name('challenge.answer');
    Route::get('{code}/peringkat', [LivePlayController::class, 'leaderboard'])->name('leaderboard');
    Route::get('{code}/papan', [LivePlayController::class, 'board'])->name('board');
    Route::get('{code}/papan/data', [LivePlayController::class, 'boardData'])->name('board.data');
    Route::get('{code}/qr.svg', [LivePlayController::class, 'joinQr'])->name('qr');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Redirect setelah login sesuai role
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->isPendingApproval()) {
            return redirect()->route('dosen.pending');
        }
        if ($user->isDosen()) {
            return redirect()->route('dosen.quizzes.index');
        }
        return redirect()->route('mahasiswa.quizzes.index');
    })->name('dashboard');
    
    // Dosen/guru baru yang menunggu persetujuan admin.
    Route::get('/menunggu-persetujuan', function () {
        $user = auth()->user();
        if (!$user->isPendingApproval()) {
            return redirect()->route('dashboard');
        }

        return view('dosen.pending', ['user' => $user]);
    })->name('dosen.pending');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/dosen', [ProfileController::class, 'addTeacher'])->name('profile.teachers.add');
    Route::delete('/profile/dosen/{teacher}', [ProfileController::class, 'removeTeacher'])->name('profile.teachers.remove');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ================= DOSEN =================
    Route::middleware('role:dosen,admin')->prefix('dosen')->name('dosen.')->group(function () {
        Route::resource('quizzes', DosenQuizController::class)->except(['show']);
        Route::resource('courses', CourseController::class)->only(['index', 'store', 'destroy']);
        // Kuis interaktif (live dan tantangan)
        Route::get('interaktif', [LiveSessionController::class, 'index'])->name('live.index');
        Route::get('interaktif/buat', [LiveSessionController::class, 'create'])->name('live.create');
        Route::post('interaktif', [LiveSessionController::class, 'store'])->name('live.store');
        Route::get('interaktif/{session}', [LiveSessionController::class, 'show'])->name('live.show');
        Route::get('interaktif/{session}/qr', [LiveSessionController::class, 'qr'])->name('live.qr');
        Route::get('interaktif/{session}/status', [LiveSessionController::class, 'status'])->name('live.status');
        Route::post('interaktif/{session}/mulai', [LiveSessionController::class, 'start'])->name('live.start');
        Route::post('interaktif/{session}/tutup', [LiveSessionController::class, 'reveal'])->name('live.reveal');
        Route::post('interaktif/{session}/lanjut', [LiveSessionController::class, 'next'])->name('live.next');
        Route::post('interaktif/{session}/akhiri', [LiveSessionController::class, 'finish'])->name('live.finish');
        Route::post('interaktif/{session}/keluarkan/{player}', [LiveSessionController::class, 'kick'])->name('live.kick');
        Route::get('interaktif/{session}/hasil', [LiveSessionController::class, 'results'])->name('live.results');
        Route::get('interaktif/{session}/export', [LiveSessionController::class, 'export'])->name('live.export');
        Route::delete('interaktif/{session}', [LiveSessionController::class, 'destroy'])->name('live.destroy');
        // Daftar kelas milik dosen
        Route::get('kelas', [TeacherClassController::class, 'index'])->name('classes.index');
        Route::post('kelas', [TeacherClassController::class, 'store'])->name('classes.store');
        Route::delete('kelas/{teacherClass}', [TeacherClassController::class, 'destroy'])->name('classes.destroy');
        // Rekap nilai per mata kuliah per periode
        Route::get('rekap', [SemesterRecapController::class, 'index'])->name('recap.index');
        Route::get('rekap/export', [SemesterRecapController::class, 'export'])->name('recap.export');
        Route::get('rekap/analitik', [SemesterRecapController::class, 'analytics'])->name('recap.analytics');
        // Peserta resmi mata kuliah per periode
        Route::get('mata-kuliah/{course}/peserta', [CourseEnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('mata-kuliah/{course}/peserta/nim', [CourseEnrollmentController::class, 'storeNims'])->name('enrollments.storeNims');
        Route::post('mata-kuliah/{course}/peserta/kelas', [CourseEnrollmentController::class, 'storeKelas'])->name('enrollments.storeKelas');
        Route::post('mata-kuliah/{course}/peserta/semua-kelas', [CourseEnrollmentController::class, 'storeAllKelas'])->name('enrollments.storeAllKelas');
        Route::delete('mata-kuliah/{course}/peserta/{enrollment}', [CourseEnrollmentController::class, 'destroy'])->name('enrollments.destroy');
        Route::post('quizzes/{quiz}/import', [QuestionImportController::class, 'store'])->name('quizzes.import');
        Route::get('quizzes/{quiz}/scores', [ScoreController::class, 'index'])->name('quizzes.scores');
        Route::get('quizzes/{quiz}/scores/export', [ScoreController::class, 'export'])->name('quizzes.scores.export');
        Route::get('quizzes/{quiz}/qrcode', [QuizQrCodeController::class, 'show'])->name('quizzes.qrcode');
        Route::get('quizzes/{quiz}/qrcode/download', [QuizQrCodeController::class, 'download'])->name('quizzes.qrcode.download');
        // Bank Soal
        Route::get('mata-kuliah/{course}/bank', [BankQuestionController::class, 'index'])->name('bank.index');
        Route::post('mata-kuliah/{course}/bank/import', [BankQuestionController::class, 'import'])->name('bank.import');
        Route::get('bank/{bankQuestion}/edit', [BankQuestionController::class, 'edit'])->name('bank.edit');
        Route::put('bank/{bankQuestion}', [BankQuestionController::class, 'update'])->name('bank.update');
        Route::delete('bank/{bankQuestion}', [BankQuestionController::class, 'destroy'])->name('bank.destroy');
        Route::get('quizzes/{quiz}/bank', [QuizBankController::class, 'picker'])->name('quizzes.bank.picker');
        Route::post('quizzes/{quiz}/bank', [QuizBankController::class, 'add'])->name('quizzes.bank.add');
        // Import Mahasiswa Massal
        Route::get('mahasiswa/import', [StudentImportController::class, 'form'])->name('students.import.form');
        Route::post('mahasiswa/import', [StudentImportController::class, 'import'])->name('students.import');
        Route::get('mahasiswa', [StudentImportController::class, 'index'])->name('students.index');
        Route::put('mahasiswa/{student}', [DosenStudentController::class, 'update'])->name('students.update');
        Route::delete('mahasiswa/{student}', [DosenStudentController::class, 'destroy'])->name('students.destroy');
        Route::put('mahasiswa/{student}/password', [DosenStudentController::class, 'updatePassword'])->name('students.password');
        // Analitik Kuis
        Route::get('quizzes/{quiz}/analytics', [QuizAnalyticsController::class, 'show'])->name('quizzes.analytics');
        Route::post('attempts/{attempt}/reset-device', [ScoreController::class, 'resetDevice'])->name('attempts.resetDevice');
        Route::post('attempts/{attempt}/cancel', [ScoreController::class, 'cancelAttempt'])->name('attempts.cancel');
        // Tambah Soal Manual (Esai/Isian/Gambar) -- kuis
        Route::get('quizzes/{quiz}/questions/create', [ManualQuestionController::class, 'create'])->name('quizzes.questions.create');
        Route::post('quizzes/{quiz}/questions', [ManualQuestionController::class, 'store'])->name('quizzes.questions.store');
        // Tambah Soal Manual -- bank soal
        Route::get('mata-kuliah/{course}/bank/tambah-manual', [BankQuestionController::class, 'createManual'])->name('bank.createManual');
        Route::post('mata-kuliah/{course}/bank/tambah-manual', [BankQuestionController::class, 'storeManual'])->name('bank.storeManual');
        // Nilai Esai
        Route::get('quizzes/{quiz}/grading', [EssayGradingController::class, 'index'])->name('grading.index');
        Route::get('attempts/{attempt}/grading', [EssayGradingController::class, 'edit'])->name('grading.edit');
        Route::put('attempts/{attempt}/grading', [EssayGradingController::class, 'update'])->name('grading.update');
        // Edit/Hapus Soal (Kuis)
        Route::get('questions/{question}/edit', [ManualQuestionController::class, 'edit'])->name('questions.edit');
        Route::put('questions/{question}', [ManualQuestionController::class, 'update'])->name('questions.update');
        Route::delete('questions/{question}', [ManualQuestionController::class, 'destroy'])->name('questions.destroy');
    });

    // ================= MAHASISWA =================
    Route::middleware('role:mahasiswa')->prefix('kuis')->name('mahasiswa.')->group(function () {
        Route::get('/', [MahasiswaQuizController::class, 'index'])->name('quizzes.index');
        Route::post('{quiz}/mulai', [MahasiswaQuizController::class, 'start'])->name('quizzes.start');
        Route::get('kerjakan/{attempt}', [MahasiswaQuizController::class, 'take'])->name('quizzes.take');
        Route::post('kerjakan/{attempt}/jawab', [MahasiswaQuizController::class, 'saveAnswer'])->name('quizzes.saveAnswer');
        Route::post('kerjakan/{attempt}/submit', [MahasiswaQuizController::class, 'submit'])->name('quizzes.submit');
        Route::get('hasil/{attempt}', [MahasiswaQuizController::class, 'result'])->name('quizzes.result');
        Route::get('gabung', [QuizJoinController::class, 'showForm'])->name('quizzes.join');
        Route::post('gabung', [QuizJoinController::class, 'submit'])->name('quizzes.join.submit');
        Route::get('gabung/{code}', [QuizJoinController::class, 'show'])->name('quizzes.found');
        Route::get('riwayat', [HistoryController::class, 'index'])->name('quizzes.history');
        Route::post('kerjakan/{attempt}/tab-switch', [MahasiswaQuizController::class, 'reportTabSwitch'])->name('quizzes.tabSwitch');
        Route::get('kerjakan/{attempt}/device-mismatch', [MahasiswaQuizController::class, 'deviceMismatch'])->name('quizzes.deviceMismatch');
        Route::post('notifikasi/{id}/baca', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    });
    
    // ================= ADMIN =================
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('dosen', [LecturerController::class, 'index'])->name('lecturers.index');
        Route::get('dosen/tambah', [LecturerController::class, 'create'])->name('lecturers.create');
        Route::post('dosen/{lecturer}/setujui', [LecturerController::class, 'approve'])->name('lecturers.approve');
        Route::delete('dosen/{lecturer}/tolak', [LecturerController::class, 'reject'])->name('lecturers.reject');
        Route::post('dosen', [LecturerController::class, 'store'])->name('lecturers.store');
        Route::get('mahasiswa', [AdminStudentController::class, 'index'])->name('students.index');
        // Periode Akademik
        Route::get('periode', [AcademicPeriodController::class, 'index'])->name('periods.index');
        Route::post('periode', [AcademicPeriodController::class, 'store'])->name('periods.store');
        Route::post('periode/{period}/aktifkan', [AcademicPeriodController::class, 'activate'])->name('periods.activate');
        Route::delete('periode/{period}', [AcademicPeriodController::class, 'destroy'])->name('periods.destroy');
        // Konversi nilai huruf
        Route::get('konversi-nilai', [GradeScaleController::class, 'index'])->name('gradeScales.index');
        Route::put('konversi-nilai', [GradeScaleController::class, 'update'])->name('gradeScales.update');
        Route::get('backup',[BackupController::class, 'index'])->name('backups.index');
        Route::post('backup/jalankan', [BackupController::class, 'runNow'])->name('backups.runNow');
        Route::get('backup/{filename}/unduh', [BackupController::class, 'download'])->name('backups.download');
    });
});

require __DIR__.'/auth.php';
