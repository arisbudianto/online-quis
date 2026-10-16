<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\AttemptFinalizer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalizeExpiredAttemptsTest extends TestCase
{
    use RefreshDatabase;

    /** Attempt dengan 2 soal PG; soal pertama dijawab benar, soal kedua tidak dijawab. */
    private function attempt(\DateTimeInterface $deadline): Attempt
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mhs = User::factory()->create(['role' => 'mahasiswa', 'nim' => uniqid('K35'), 'kelas' => 'A']);
        $quiz = Quiz::create(['dosen_id' => $dosen->id, 'title' => 'K', 'duration_minutes' => 10, 'max_attempts' => 1, 'is_published' => true]);

        $q1 = Question::create(['quiz_id' => $quiz->id, 'question_text' => '1', 'type' => 'pilihan_ganda', 'order' => 0]);
        $benar = Option::create(['question_id' => $q1->id, 'option_text' => 'a', 'is_correct' => true, 'order' => 0]);
        $salah1 = Option::create(['question_id' => $q1->id, 'option_text' => 'b', 'is_correct' => false, 'order' => 1]);
        $q2 = Question::create(['quiz_id' => $quiz->id, 'question_text' => '2', 'type' => 'pilihan_ganda', 'order' => 1]);
        $o2a = Option::create(['question_id' => $q2->id, 'option_text' => 'a', 'is_correct' => true, 'order' => 0]);
        $o2b = Option::create(['question_id' => $q2->id, 'option_text' => 'b', 'is_correct' => false, 'order' => 1]);

        $attempt = Attempt::create([
            'quiz_id' => $quiz->id, 'user_id' => $mhs->id,
            'question_order' => [$q1->id, $q2->id],
            'started_at' => now()->subHour(), 'deadline_at' => $deadline,
            'status' => 'in_progress',
        ]);

        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $q1->id, 'option_order' => [$benar->id, $salah1->id], 'selected_option_id' => $benar->id]);
        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $q2->id, 'option_order' => [$o2a->id, $o2b->id]]);

        return $attempt;
    }

    public function test_expired_attempt_is_finalized_with_score(): void
    {
        $attempt = $this->attempt(now()->subMinutes(10));

        $this->artisan('attempts:finalize-expired')->assertSuccessful();

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertNotNull($attempt->submitted_at);
        $this->assertEquals(50.0, (float) $attempt->score, 'Satu benar dari dua soal = 50.');
    }

    public function test_attempt_within_grace_period_is_left_alone(): void
    {
        $attempt = $this->attempt(now()->subMinute());

        $this->artisan('attempts:finalize-expired')->assertSuccessful();

        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_running_attempt_is_left_alone(): void
    {
        $attempt = $this->attempt(now()->addMinutes(20));

        $this->artisan('attempts:finalize-expired')->assertSuccessful();

        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $attempt = $this->attempt(now()->subMinutes(10));

        $this->artisan('attempts:finalize-expired', ['--dry-run' => true])
            ->expectsOutputToContain('1 attempt')
            ->assertSuccessful();

        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_finalizer_does_not_regrade_closed_attempt(): void
    {
        $attempt = $this->attempt(now()->subMinutes(10));
        $finalizer = app(AttemptFinalizer::class);

        $this->assertTrue($finalizer->finalize($attempt));
        $firstSubmittedAt = $attempt->fresh()->submitted_at;

        $this->travel(5)->minutes();
        $this->assertFalse($finalizer->finalize($attempt->fresh()));
        $this->assertEquals($firstSubmittedAt, $attempt->fresh()->submitted_at);
    }

    public function test_command_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'attempts:finalize-expired'));

        $this->assertCount(1, $events);
    }
}
