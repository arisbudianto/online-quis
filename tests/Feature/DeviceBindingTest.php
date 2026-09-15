<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceBindingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSimpleQuiz(User $dosen): Quiz
    {
        $course = Course::create(['dosen_id' => $dosen->id, 'name' => 'MK Device Test']);

        $quiz = Quiz::create([
            'dosen_id' => $dosen->id,
            'course_id' => $course->id,
            'title' => 'Kuis Device Binding',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'is_published' => true,
        ]);

        $q = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Q?', 'type' => 'pilihan_ganda', 'order' => 0]);
        Option::create(['question_id' => $q->id, 'option_text' => 'A', 'is_correct' => true, 'order' => 0]);
        Option::create(['question_id' => $q->id, 'option_text' => 'B', 'is_correct' => false, 'order' => 1]);

        return $quiz;
    }

    /**
     * Attempt yang browser_session_id-nya SUDAH TERIKAT ke sesi lain (bukan
     * sesi request saat ini) HARUS ditolak akses ke halaman kerjakan --
     * ini logika inti anti-kecurangan "1 device per attempt".
     */
    public function test_attempt_from_different_session_is_blocked(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => '2024000333', 'kelas' => 'A']);
        $quiz = $this->makeSimpleQuiz($dosen);

        // Buat attempt LANGSUNG lewat Eloquent dengan browser_session_id
        // palsu yang PASTI beda dari sesi request test ini (bukan lewat
        // endpoint start(), supaya session_id-nya kita kontrol penuh).
        $attempt = Attempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $mahasiswa->id,
            'question_order' => $quiz->questions()->pluck('id')->all(),
            'started_at' => now(),
            'deadline_at' => now()->addHour(),
            'status' => 'in_progress',
            'browser_session_id' => 'sesi-device-lain-yang-berbeda',
        ]);

        $this->actingAs($mahasiswa)
            ->get(route('mahasiswa.quizzes.take', $attempt))
            ->assertRedirect(route('mahasiswa.quizzes.deviceMismatch', $attempt));
    }

    /**
     * Attempt yang dimulai lewat endpoint start() (session_id terisi dari
     * request yang sama) HARUS TETAP BISA diakses dari sesi yang sama --
     * mahasiswa asli tidak boleh ikut ke-block oleh proteksinya sendiri.
     */
    public function test_attempt_from_same_session_is_allowed(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => '2024000444', 'kelas' => 'B']);
        $quiz = $this->makeSimpleQuiz($dosen);

        $this->actingAs($mahasiswa);
        $this->post(route('mahasiswa.quizzes.start', $quiz));
        $this->continueSession();

        $attempt = Attempt::where('quiz_id', $quiz->id)->where('user_id', $mahasiswa->id)->firstOrFail();

        // Request kedua ini meneruskan cookie sesi yang sama dari start() di
        // atas -- harus 200 OK, BUKAN diarahkan ke halaman device-mismatch.
        $this->get(route('mahasiswa.quizzes.take', $attempt))
            ->assertOk();
    }

    /**
     * Dosen bisa "Reset Device" attempt yang macet -- setelah di-reset,
     * browser_session_id jadi null, attempt bisa diakses dari sesi manapun
     * lagi (device baru akan terikat otomatis di request berikutnya kalau
     * kodenya memang begitu -- di sini kita cukup pastikan reset berhasil
     * menghapus ikatan lamanya).
     */
    public function test_dosen_can_reset_device_binding(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => '2024000555', 'kelas' => 'C']);
        $quiz = $this->makeSimpleQuiz($dosen);

        $attempt = Attempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $mahasiswa->id,
            'question_order' => $quiz->questions()->pluck('id')->all(),
            'started_at' => now(),
            'deadline_at' => now()->addHour(),
            'status' => 'in_progress',
            'browser_session_id' => 'sesi-lama',
        ]);

        $this->actingAs($dosen)
            ->post(route('dosen.attempts.resetDevice', $attempt))
            ->assertRedirect();

        $this->assertNull($attempt->fresh()->browser_session_id);
    }
}
