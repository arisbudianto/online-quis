<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateQuizAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuiz(bool $isPublic): Quiz
    {
        $dosen = User::factory()->create(['role' => 'dosen']);

        $quiz = Quiz::create([
            'dosen_id' => $dosen->id,
            'title' => 'Kuis ' . uniqid(),
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'is_published' => true,
            'is_public' => $isPublic,
        ]);

        $q = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Q?', 'type' => 'pilihan_ganda', 'order' => 0]);
        Option::create(['question_id' => $q->id, 'option_text' => 'A', 'is_correct' => true, 'order' => 0]);
        Option::create(['question_id' => $q->id, 'option_text' => 'B', 'is_correct' => false, 'order' => 1]);

        return $quiz;
    }

    private function mahasiswa(): User
    {
        return User::factory()->create(['role' => 'mahasiswa', 'nim' => uniqid('K35'), 'kelas' => 'A']);
    }

    public function test_private_quiz_cannot_be_started_without_code(): void
    {
        $quiz = $this->makeQuiz(false);
        $mhs = $this->mahasiswa();

        $this->actingAs($mhs)
            ->post(route('mahasiswa.quizzes.start', $quiz))
            ->assertForbidden();

        $this->assertSame(0, Attempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_private_quiz_cannot_be_started_with_wrong_code(): void
    {
        $quiz = $this->makeQuiz(false);

        $this->actingAs($this->mahasiswa())
            ->post(route('mahasiswa.quizzes.start', $quiz), ['code' => 'ZZZZZZ'])
            ->assertForbidden();

        $this->assertSame(0, Attempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_private_quiz_can_be_started_with_correct_code_in_any_case(): void
    {
        $quiz = $this->makeQuiz(false);
        $mhs = $this->mahasiswa();

        $this->actingAs($mhs)
            ->post(route('mahasiswa.quizzes.start', $quiz), ['code' => strtolower($quiz->code)])
            ->assertRedirect();

        $this->assertSame(1, Attempt::where('quiz_id', $quiz->id)->where('user_id', $mhs->id)->count());
    }

    public function test_public_quiz_still_starts_without_code(): void
    {
        $quiz = $this->makeQuiz(true);

        $this->actingAs($this->mahasiswa())
            ->post(route('mahasiswa.quizzes.start', $quiz))
            ->assertRedirect();

        $this->assertSame(1, Attempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_found_page_form_carries_the_code(): void
    {
        $quiz = $this->makeQuiz(false);

        $this->actingAs($this->mahasiswa())
            ->post(route('mahasiswa.quizzes.join.submit'), ['code' => $quiz->code])
            ->assertOk()
            ->assertSee('name="code" value="' . $quiz->code . '"', false);
    }
}
