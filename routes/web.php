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
use App\Http\Controllers\Mahasiswa\HistoryController;
use App\Http\Controllers\Mahasiswa\QuizController as MahasiswaQuizController;
use App\Http\Controllers\Mahasiswa\QuizJoinController;
use App\Http\Controllers\Mahasiswa\NotificationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LecturerController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\AcademicPeriodController;
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

Route::middleware(['auth', 'verified'])->group(function () {

    // Redirect setelah login sesuai role
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->isDosen()) {
            return redirect()->route('dosen.quizzes.index');
        }
        return redirect()->route('mahasiswa.quizzes.index');
    })->name('dashboard');
    
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ================= DOSEN =================
    Route::middleware('role:dosen,admin')->prefix('dosen')->name('dosen.')->group(function () {
        Route::resource('quizzes', DosenQuizController::class)->except(['show']);
        Route::resource('courses', CourseController::class)->only(['index', 'store', 'destroy']);
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
        Route::post('dosen', [LecturerController::class, 'store'])->name('lecturers.store');
        Route::get('mahasiswa', [AdminStudentController::class, 'index'])->name('students.index');
        // Periode Akademik
        Route::get('periode', [AcademicPeriodController::class, 'index'])->name('periods.index');
        Route::post('periode', [AcademicPeriodController::class, 'store'])->name('periods.store');
        Route::post('periode/{period}/aktifkan', [AcademicPeriodController::class, 'activate'])->name('periods.activate');
        Route::delete('periode/{period}', [AcademicPeriodController::class, 'destroy'])->name('periods.destroy');
        Route::get('backup',[BackupController::class, 'index'])->name('backups.index');
        Route::post('backup/jalankan', [BackupController::class, 'runNow'])->name('backups.runNow');
        Route::get('backup/{filename}/unduh', [BackupController::class, 'download'])->name('backups.download');
    });
});

require __DIR__.'/auth.php';
