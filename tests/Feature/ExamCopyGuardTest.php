<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamCopyGuardTest extends TestCase
{
    use RefreshDatabase;

    private function startedAttempt(): array
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $quiz = Quiz::create([
            'dosen_id' => $dosen->id, 'title' => 'K', 'duration_minutes' => 30,
            'max_attempts' => 1, 'is_published' => true, 'is_public' => true,
        ]);
        $q = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Q', 'type' => 'pilihan_ganda', 'order' => 0]);
        Option::create(['question_id' => $q->id, 'option_text' => 'a', 'is_correct' => true, 'order' => 0]);
        Option::create(['question_id' => $q->id, 'option_text' => 'b', 'is_correct' => false, 'order' => 1]);

        $mhs = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'K3526001', 'kelas' => 'A']);
        $this->withCookie('qz_device_id', 'perangkat-1');
        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz));

        return [$mhs, Attempt::firstOrFail()];
    }

    public function test_take_page_has_copy_and_right_click_guard(): void
    {
        [$mhs, $attempt] = $this->startedAttempt();

        $this->actingAs($mhs)->get(route('mahasiswa.quizzes.take', $attempt))
            ->assertOk()
            ->assertSee("classList.add('exam-protected')", false)
            ->assertSee("addEventListener('contextmenu'", false)
            ->assertSee("['copy', 'cut']", false);
    }

    public function test_result_page_is_not_restricted(): void
    {
        [$mhs, $attempt] = $this->startedAttempt();
        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.submit', $attempt));

        $this->actingAs($mhs)->get(route('mahasiswa.quizzes.result', $attempt))
            ->assertOk()
            ->assertDontSee("classList.add('exam-protected')", false);
    }
}
