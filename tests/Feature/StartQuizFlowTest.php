<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Insiden 16-10-2026: kuis 10 terbit tanpa soal. Mahasiswa menemukan kuis
 * lewat kode, menekan Mulai, mendapat halaman error 422, menekan Back,
 * lalu mendapat error lagi atau "Page Expired" (419).
 */
class StartQuizFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->create(['role' => 'dosen']);
        $this->withCookie('qz_device_id', 'perangkat-1');
    }

    private function quiz(bool $withQuestion = true, array $attrs = []): Quiz
    {
        $quiz = Quiz::create(array_merge([
            'dosen_id' => $this->dosen->id, 'title' => 'Learner with Exception', 'duration_minutes' => 30,
            'max_attempts' => 1, 'is_published' => true, 'is_public' => false,
        ], $attrs));

        if ($withQuestion) {
            $q = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Q', 'type' => 'pilihan_ganda', 'order' => 0]);
            Option::create(['question_id' => $q->id, 'option_text' => 'a', 'is_correct' => true, 'order' => 0]);
            Option::create(['question_id' => $q->id, 'option_text' => 'b', 'is_correct' => false, 'order' => 1]);
        }

        return $quiz;
    }

    private function mhs(): User
    {
        return User::factory()->create(['role' => 'mahasiswa', 'nim' => uniqid('K35'), 'kelas' => 'A']);
    }

    public function test_join_redirects_to_get_page_that_survives_back_button(): void
    {
        $quiz = $this->quiz();
        $mhs = $this->mhs();

        $this->actingAs($mhs)
            ->post(route('mahasiswa.quizzes.join.submit'), ['code' => strtolower($quiz->code)])
            ->assertRedirect(route('mahasiswa.quizzes.found', $quiz->code));

        $res = $this->actingAs($mhs)->get(route('mahasiswa.quizzes.found', $quiz->code));
        $res->assertOk()->assertSee('Mulai Kuis');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_unknown_code_page_returns_to_join_form_with_message(): void
    {
        $this->actingAs($this->mhs())
            ->get(route('mahasiswa.quizzes.found', 'ZZZZZZ'))
            ->assertRedirect(route('mahasiswa.quizzes.join'))
            ->assertSessionHasErrors('code');
    }

    public function test_quiz_without_questions_shows_message_instead_of_start_button(): void
    {
        $quiz = $this->quiz(false);

        $this->actingAs($this->mhs())
            ->get(route('mahasiswa.quizzes.found', $quiz->code))
            ->assertOk()
            ->assertSee('belum berisi soal')
            ->assertDontSee('Mulai Kuis');
    }

    public function test_starting_quiz_without_questions_redirects_with_message_not_error_page(): void
    {
        $quiz = $this->quiz(false);
        $mhs = $this->mhs();

        $this->actingAs($mhs)
            ->post(route('mahasiswa.quizzes.start', $quiz), ['code' => $quiz->code])
            ->assertRedirect(route('mahasiswa.quizzes.found', $quiz->code))
            ->assertSessionHasErrors('quiz');

        $this->assertSame(0, Attempt::count());
    }

    public function test_back_from_running_quiz_then_start_again_resumes(): void
    {
        $quiz = $this->quiz();
        $mhs = $this->mhs();

        $this->actingAs($mhs)->post(route('mahasiswa.quizzes.start', $quiz), ['code' => $quiz->code]);
        $attempt = Attempt::firstOrFail();

        // Back ke halaman kuis ditemukan: tombol berubah jadi Lanjutkan.
        $this->actingAs($mhs)->get(route('mahasiswa.quizzes.found', $quiz->code))
            ->assertOk()->assertSee('Lanjutkan Kuis')->assertDontSee('Mulai Kuis');

        $this->actingAs($mhs)
            ->post(route('mahasiswa.quizzes.start', $quiz), ['code' => $quiz->code])
            ->assertRedirect(route('mahasiswa.quizzes.take', $attempt));

        $this->assertSame(1, Attempt::count());
    }

    public function test_closed_quiz_redirects_with_message(): void
    {
        $quiz = $this->quiz(true, ['is_public' => true, 'end_time' => now()->subDay()]);

        $this->actingAs($this->mhs())
            ->post(route('mahasiswa.quizzes.start', $quiz))
            ->assertRedirect(route('mahasiswa.quizzes.index'))
            ->assertSessionHasErrors('quiz');
    }

    public function test_dosen_cannot_publish_quiz_without_questions(): void
    {
        $quiz = $this->quiz(false, ['is_published' => false]);
        $payload = ['title' => 'K', 'duration_minutes' => 30, 'is_published' => 1];

        $this->actingAs($this->dosen)->put(route('dosen.quizzes.update', $quiz), $payload)
            ->assertSessionHasErrors('is_published');
        $this->assertFalse($quiz->fresh()->is_published);

        // Simpan sebagai draft tetap bisa.
        $this->actingAs($this->dosen)->put(route('dosen.quizzes.update', $quiz), ['title' => 'K2', 'duration_minutes' => 30])
            ->assertSessionHasNoErrors();
        $this->assertSame('K2', $quiz->fresh()->title);

        // Setelah ada soal, boleh terbit.
        Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Q', 'type' => 'esai', 'order' => 0]);
        $this->actingAs($this->dosen)->put(route('dosen.quizzes.update', $quiz), $payload)
            ->assertSessionHasNoErrors();
        $this->assertTrue($quiz->fresh()->is_published);
    }

    public function test_dosen_list_flags_published_quiz_without_questions(): void
    {
        $this->quiz(false, ['title' => 'KUIS-KOSONG']);

        $this->actingAs($this->dosen)->get(route('dosen.quizzes.index'))
            ->assertOk()->assertSee('Terbit tanpa soal');
    }
}
