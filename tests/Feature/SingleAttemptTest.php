<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Aturan pemilik aplikasi (16-10-2026): mahasiswa hanya boleh mengerjakan satu kali. */
class SingleAttemptTest extends TestCase
{
    use RefreshDatabase;

    private function quiz(array $attrs = []): Quiz
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $quiz = Quiz::create(array_merge([
            'dosen_id' => $dosen->id, 'title' => 'K', 'duration_minutes' => 30,
            'max_attempts' => 1, 'is_published' => true,
        ], $attrs));
        $q = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Q', 'type' => 'pilihan_ganda', 'order' => 0]);
        Option::create(['question_id' => $q->id, 'option_text' => 'a', 'is_correct' => true, 'order' => 0]);
        Option::create(['question_id' => $q->id, 'option_text' => 'b', 'is_correct' => false, 'order' => 1]);

        return $quiz;
    }

    private function mhs(): User
    {
        return User::factory()->create(['role' => 'mahasiswa', 'nim' => uniqid('K35'), 'kelas' => 'A']);
    }

    public function test_pressing_start_again_resumes_running_attempt(): void
    {
        $quiz = $this->quiz();
        $mhs = $this->mhs();
        $this->withCookie('qz_device_id', 'perangkat-1');

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz));
        $attempt = Attempt::where('quiz_id', $quiz->id)->firstOrFail();

        $this->actingAs($mhs)
            ->post(route('mahasiswa.quizzes.start', $quiz))
            ->assertRedirect(route('mahasiswa.quizzes.take', $attempt));

        $this->assertSame(1, Attempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_cannot_start_again_after_submitting(): void
    {
        $quiz = $this->quiz();
        $mhs = $this->mhs();
        $this->withCookie('qz_device_id', 'perangkat-1');

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz));
        $attempt = Attempt::where('quiz_id', $quiz->id)->firstOrFail();
        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.submit', $attempt));

        $this->actingAs($mhs)
            ->post(route('mahasiswa.quizzes.start', $quiz))
            ->assertRedirect(route('mahasiswa.quizzes.index'))
            ->assertSessionHasErrors('quiz');

        $this->assertSame(1, Attempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_legacy_quiz_with_higher_limit_still_allows_only_one(): void
    {
        // Kuis lama yang dulu diset 3 kali tetap hanya boleh 1 kali.
        $quiz = $this->quiz(['max_attempts' => 3]);
        $mhs = $this->mhs();
        $this->withCookie('qz_device_id', 'perangkat-1');

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz));
        $attempt = Attempt::where('quiz_id', $quiz->id)->firstOrFail();
        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.submit', $attempt));

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz))->assertSessionHasErrors('quiz');
        $this->assertSame(1, Attempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_expired_running_attempt_is_closed_not_restarted(): void
    {
        $quiz = $this->quiz();
        $mhs = $this->mhs();
        $this->withCookie('qz_device_id', 'perangkat-1');

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz));
        $attempt = Attempt::where('quiz_id', $quiz->id)->firstOrFail();

        $this->travel(31)->minutes();

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz))
            ->assertRedirect(route('mahasiswa.quizzes.take', $attempt));

        $this->assertSame(1, Attempt::where('quiz_id', $quiz->id)->count());
    }

    public function test_dosen_cannot_set_more_than_one_attempt(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);

        $this->actingAs($dosen)->post(route('dosen.quizzes.store'), [
            'title' => 'Baru', 'duration_minutes' => 30, 'max_attempts' => 5,
        ]);
        $quiz = Quiz::where('title', 'Baru')->firstOrFail();
        $this->assertSame(1, $quiz->max_attempts);

        $this->actingAs($dosen)->put(route('dosen.quizzes.update', $quiz), [
            'title' => 'Baru', 'duration_minutes' => 30, 'max_attempts' => 4,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $quiz->fresh()->max_attempts);
    }

    public function test_dosen_cancel_frees_the_single_slot(): void
    {
        $quiz = $this->quiz();
        $mhs = $this->mhs();
        $this->withCookie('qz_device_id', 'perangkat-1');

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz));
        $attempt = Attempt::where('quiz_id', $quiz->id)->firstOrFail();

        $this->actingAs($quiz->dosen)->post(route('dosen.attempts.cancel', $attempt));
        $this->assertSame(0, Attempt::where('quiz_id', $quiz->id)->count());

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz))->assertRedirect();
        $this->assertSame(1, Attempt::where('quiz_id', $quiz->id)->count());
    }
}
