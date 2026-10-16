<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimezoneAndKelasTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_runs_in_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('+07:00', now()->format('P'));
    }

    public function test_quiz_schedule_entered_in_wib_opens_at_that_wib_time(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $quiz = Quiz::create([
            'dosen_id' => $dosen->id, 'title' => 'K', 'duration_minutes' => 30, 'max_attempts' => 1,
            'is_published' => true,
            'start_time' => '2026-10-20 07:00:00',  // diketik dosen: jam 07:00 WIB
            'end_time' => '2026-10-20 23:59:00',
        ]);

        // 06:59 WIB: belum buka. Dulu (UTC) baru buka 14:00 WIB.
        Carbon::setTestNow(Carbon::parse('2026-10-20 06:59:00', 'Asia/Jakarta'));
        $this->assertFalse($quiz->fresh()->isOpenNow());

        Carbon::setTestNow(Carbon::parse('2026-10-20 07:00:00', 'Asia/Jakarta'));
        $this->assertTrue($quiz->fresh()->isOpenNow());

        // 23:59 WIB masih buka, 00:00 WIB besoknya tutup.
        Carbon::setTestNow(Carbon::parse('2026-10-20 23:59:00', 'Asia/Jakarta'));
        $this->assertTrue($quiz->fresh()->isOpenNow());
        Carbon::setTestNow(Carbon::parse('2026-10-21 00:00:01', 'Asia/Jakarta'));
        $this->assertFalse($quiz->fresh()->isOpenNow());

        Carbon::setTestNow();
    }

    public function test_shift_migration_moves_recorded_times_but_not_quiz_schedule(): void
    {
        $migration = require database_path('migrations/2026_10_16_000006_shift_recorded_times_to_wib.php');

        $dosen = User::factory()->create(['role' => 'dosen']);
        DB::table('users')->where('id', $dosen->id)->update([
            'created_at' => '2026-10-06 19:07:13', 'updated_at' => '2026-10-06 19:07:13', 'email_verified_at' => null,
        ]);
        $quizId = DB::table('quizzes')->insertGetId([
            'dosen_id' => $dosen->id, 'title' => 'K', 'duration_minutes' => 30, 'max_attempts' => 1,
            'start_time' => '2026-10-13 00:00:00', 'end_time' => '2026-10-20 23:59:00',
            'created_at' => '2026-10-13 01:42:28', 'updated_at' => '2026-10-13 01:42:28',
        ]);
        $mhs = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'K3526001', 'kelas' => 'A']);
        $attemptId = DB::table('attempts')->insertGetId([
            'quiz_id' => $quizId, 'user_id' => $mhs->id, 'question_order' => '[]', 'status' => 'submitted',
            'started_at' => '2026-10-06 19:07:13', 'deadline_at' => '2026-10-06 19:37:13', 'submitted_at' => null,
            'created_at' => '2026-10-06 19:07:13', 'updated_at' => '2026-10-06 19:16:25',
        ]);

        $migration->up();

        $a = DB::table('attempts')->find($attemptId);
        $this->assertSame('2026-10-07 02:07:13', $a->started_at);
        $this->assertSame('2026-10-07 02:37:13', $a->deadline_at);
        $this->assertNull($a->submitted_at, 'NULL tetap NULL');
        $this->assertSame('2026-10-07 02:16:25', $a->updated_at);

        $q = DB::table('quizzes')->find($quizId);
        $this->assertSame('2026-10-13 00:00:00', $q->start_time, 'Jadwal dosen tidak digeser');
        $this->assertSame('2026-10-20 23:59:00', $q->end_time);
        $this->assertSame('2026-10-13 08:42:28', $q->created_at);

        $this->assertSame('2026-10-07 02:07:13', DB::table('users')->where('id', $dosen->id)->value('created_at'));
        $this->assertNull(DB::table('users')->where('id', $dosen->id)->value('email_verified_at'));

        $migration->down();
        $this->assertSame('2026-10-06 19:07:13', DB::table('attempts')->where('id', $attemptId)->value('started_at'));
        $this->assertSame('2026-10-13 00:00:00', DB::table('quizzes')->where('id', $quizId)->value('start_time'));
    }

    public function test_registration_offers_only_a_b_c_and_rejects_d(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('<option value="C"', false)
            ->assertDontSee('<option value="D"', false);

        $this->post('/register', [
            'name' => 'Mhs D', 'nim' => 'K3526999', 'kelas' => 'D', 'email' => 'd@contoh.test',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('kelas');

        $this->assertSame(0, User::where('nim', 'K3526999')->count());
    }

    public function test_dosen_cannot_set_unknown_kelas_on_student(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mhs = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'K3526010', 'kelas' => 'A']);

        $this->actingAs($dosen)->put(route('dosen.students.update', $mhs), [
            'name' => $mhs->name, 'nim' => $mhs->nim, 'kelas' => 'TI-3A', 'email' => $mhs->email,
        ])->assertSessionHasErrors('kelas');
        $this->assertSame('A', $mhs->fresh()->kelas);

        $this->actingAs($dosen)->put(route('dosen.students.update', $mhs), [
            'name' => $mhs->name, 'nim' => $mhs->nim, 'kelas' => 'C', 'email' => $mhs->email,
        ])->assertSessionHasNoErrors();
        $this->assertSame('C', $mhs->fresh()->kelas);
    }

    public function test_target_kelas_rejects_d(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $quiz = Quiz::create(['dosen_id' => $dosen->id, 'title' => 'K', 'duration_minutes' => 30, 'max_attempts' => 1]);

        $this->actingAs($dosen)->put(route('dosen.quizzes.update', $quiz), [
            'title' => 'K', 'duration_minutes' => 30, 'target_kelas' => ['A', 'D'],
        ])->assertSessionHasErrors('target_kelas.1');
    }
}
