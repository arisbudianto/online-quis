<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortAnswerGradingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider answerVariations
     */
    public function test_short_answer_matching_is_case_insensitive_and_trims_whitespace(string $studentAnswer, bool $shouldBeCorrect): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => uniqid('nim'), 'kelas' => 'A']);
        $course = Course::create(['dosen_id' => $dosen->id, 'name' => 'MK Isian Test']);

        $quiz = Quiz::create([
            'dosen_id' => $dosen->id,
            'course_id' => $course->id,
            'title' => 'Kuis Isian ' . uniqid(),
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'is_published' => true,
        ]);

        Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Ibu kota Indonesia?',
            'type' => 'isian_singkat',
            'correct_text_answer' => 'Jakarta',
            'order' => 0,
        ]);

        $this->actingAs($mahasiswa);
        // Browser nyata mengirim cookie perangkat yang sama di setiap request.
        $this->withCookie('qz_device_id', 'perangkat-test-1');
        $this->post(route('mahasiswa.quizzes.start', $quiz));
        $this->continueSession();

        $attempt = Attempt::where('quiz_id', $quiz->id)->where('user_id', $mahasiswa->id)->firstOrFail();
        $answer = $attempt->answers()->first();

        $this->post(route('mahasiswa.quizzes.saveAnswer', $attempt), [
            'answer_id' => $answer->id,
            'answer_text' => $studentAnswer,
        ]);

        $this->post(route('mahasiswa.quizzes.submit', $attempt));

        $this->assertEquals($shouldBeCorrect, (bool) $answer->fresh()->is_correct, "Jawaban \"{$studentAnswer}\" seharusnya " . ($shouldBeCorrect ? 'BENAR' : 'SALAH'));
    }

    public static function answerVariations(): array
    {
        return [
            'jawaban persis sama' => ['Jakarta', true],
            'huruf kecil semua' => ['jakarta', true],
            'huruf besar semua' => ['JAKARTA', true],
            'ada spasi di awal/akhir' => ['  Jakarta  ', true],
            'ada spasi ganda di tengah (kasus tidak umum tapi harus tetap toleran)' => ['Jakarta', true],
            'jawaban salah total' => ['Bandung', false],
            'jawaban kosong' => ['', false],
        ];
    }
}
