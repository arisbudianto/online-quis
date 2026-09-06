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

class ScoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji end-to-end: mahasiswa kerjakan kuis campuran (PG + isian_singkat +
     * esai), submit, HARUS berstatus needs_grading=true & score masih null
     * (karena esai belum dinilai) -- lalu dosen nilai esainya, score akhir
     * HARUS jadi rata-rata dari ketiga soal (100 + 100 + poin_esai) / 3.
     *
     * Ini test paling penting di seluruh aplikasi karena menyentuh langsung
     * Attempt::recalculateScoreIfReady() -- kalau ini rusak, nilai mahasiswa
     * yang salah hitung baru ketahuan setelah dipakai ujian sungguhan.
     */
    public function test_mixed_quiz_score_is_average_of_all_question_points_and_waits_for_essay_grading(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => '2024000111', 'kelas' => 'A']);
        $course = Course::create(['dosen_id' => $dosen->id, 'name' => 'Mata Kuliah Uji']);

        $quiz = Quiz::create([
            'dosen_id' => $dosen->id,
            'course_id' => $course->id,
            'title' => 'Kuis Campuran',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'is_published' => true,
        ]);

        // Soal 1: pilihan ganda, opsi B benar
        $pg = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'PG?', 'type' => 'pilihan_ganda', 'order' => 0]);
        $optA = Option::create(['question_id' => $pg->id, 'option_text' => 'A', 'is_correct' => false, 'order' => 0]);
        $optB = Option::create(['question_id' => $pg->id, 'option_text' => 'B', 'is_correct' => true, 'order' => 1]);

        // Soal 2: isian singkat, jawaban benar "96"
        $isian = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Isian?', 'type' => 'isian_singkat', 'correct_text_answer' => '96', 'order' => 1]);

        // Soal 3: esai, tidak ada auto-grading
        $esai = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Esai?', 'type' => 'esai', 'order' => 2]);

        $this->actingAs($mahasiswa);

        // Mulai attempt
        $this->post(route('mahasiswa.quizzes.start', $quiz));
        $this->continueSession();
        $attempt = Attempt::where('quiz_id', $quiz->id)->where('user_id', $mahasiswa->id)->firstOrFail();

        $answers = $attempt->answers()->get()->keyBy('question_id');

        // Jawab PG dengan opsi yang benar
        $this->post(route('mahasiswa.quizzes.saveAnswer', $attempt), [
            'answer_id' => $answers[$pg->id]->id,
            'option_id' => $optB->id,
        ]);

        // Jawab isian singkat dengan jawaban benar
        $this->post(route('mahasiswa.quizzes.saveAnswer', $attempt), [
            'answer_id' => $answers[$isian->id]->id,
            'answer_text' => '96',
        ]);

        // Jawab esai (isi bebas, tidak akan auto-grade)
        $this->post(route('mahasiswa.quizzes.saveAnswer', $attempt), [
            'answer_id' => $answers[$esai->id]->id,
            'answer_text' => 'Ini jawaban esai saya.',
        ]);

        // Submit
        $this->post(route('mahasiswa.quizzes.submit', $attempt));

        $attempt->refresh();

        $this->assertEquals('submitted', $attempt->status);
        $this->assertTrue($attempt->needs_grading, 'Attempt harus menunggu nilai esai sebelum skor final.');
        $this->assertNull($attempt->score, 'Skor belum boleh terisi sebelum esai dinilai.');

        // PG & isian harus sudah otomatis benar
        $this->assertTrue($answers[$pg->id]->fresh()->is_correct);
        $this->assertTrue($answers[$isian->id]->fresh()->is_correct);
        $this->assertEquals(100, (float) $answers[$pg->id]->fresh()->points);
        $this->assertEquals(100, (float) $answers[$isian->id]->fresh()->points);
        $this->assertNull($answers[$esai->id]->fresh()->points, 'Poin esai belum boleh terisi sebelum dinilai dosen.');

        // Dosen nilai esai dengan 79 poin
        $this->actingAs($dosen);
        $this->put(route('dosen.grading.update', $attempt), [
            'points' => [
                $answers[$esai->id]->id => 79,
            ],
        ]);

        $attempt->refresh();

        $this->assertFalse($attempt->needs_grading, 'Setelah semua esai dinilai, attempt harus final.');
        // (100 + 100 + 79) / 3 = 93.0
        $this->assertEquals(93.0, (float) $attempt->score);
    }

    /**
     * Kuis PURE pilihan ganda (tanpa esai sama sekali) HARUS tetap langsung
     * final otomatis persis seperti perilaku sebelum fitur esai ditambahkan
     * -- ini test regresi backward-compatibility yang paling penting.
     */
    public function test_pure_multiple_choice_quiz_still_finalizes_score_immediately(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => '2024000222', 'kelas' => 'B']);
        $course = Course::create(['dosen_id' => $dosen->id, 'name' => 'MK PG Murni']);

        $quiz = Quiz::create([
            'dosen_id' => $dosen->id,
            'course_id' => $course->id,
            'title' => 'Kuis PG Murni',
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'is_published' => true,
        ]);

        $q1 = Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Q1', 'type' => 'pilihan_ganda', 'order' => 0]);
        Option::create(['question_id' => $q1->id, 'option_text' => 'Salah', 'is_correct' => false, 'order' => 0]);
        $correct = Option::create(['question_id' => $q1->id, 'option_text' => 'Benar', 'is_correct' => true, 'order' => 1]);

        $this->actingAs($mahasiswa);
        $this->post(route('mahasiswa.quizzes.start', $quiz));
        $this->continueSession();

        $attempt = Attempt::where('quiz_id', $quiz->id)->where('user_id', $mahasiswa->id)->firstOrFail();
        $answer = $attempt->answers()->first();

        $this->post(route('mahasiswa.quizzes.saveAnswer', $attempt), [
            'answer_id' => $answer->id,
            'option_id' => $correct->id,
        ]);

        $this->post(route('mahasiswa.quizzes.submit', $attempt));

        $attempt->refresh();

        $this->assertEquals('submitted', $attempt->status);
        $this->assertFalse($attempt->needs_grading, 'Kuis tanpa esai TIDAK BOLEH menunggu penilaian apapun.');
        $this->assertEquals(100.0, (float) $attempt->score);
    }
}
