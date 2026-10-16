<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function dosen(): User
    {
        return User::factory()->create(['role' => 'dosen']);
    }

    private function basePayload(array $extra = []): array
    {
        return array_merge([
            'title' => 'Kuis Periode',
            'duration_minutes' => 30,
            'max_attempts' => 1,
        ], $extra);
    }

    private function quiz(User $dosen, array $attrs = []): Quiz
    {
        return Quiz::create(array_merge([
            'dosen_id' => $dosen->id,
            'title' => 'Kuis ' . uniqid(),
            'duration_minutes' => 10,
            'max_attempts' => 1,
        ], $attrs));
    }

    public function test_create_form_preselects_active_period(): void
    {
        $old = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap']);
        $active = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil', 'is_active' => true]);

        $this->actingAs($this->dosen())
            ->get(route('dosen.quizzes.create'))
            ->assertOk()
            ->assertSee('value="' . $active->id . '" selected', false)
            ->assertSee('2025/2026 Genap');
    }

    public function test_store_without_field_uses_active_period(): void
    {
        $active = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil', 'is_active' => true]);
        $dosen = $this->dosen();

        $this->actingAs($dosen)->post(route('dosen.quizzes.store'), $this->basePayload());

        $this->assertSame($active->id, Quiz::where('dosen_id', $dosen->id)->firstOrFail()->academic_period_id);
    }

    public function test_store_with_chosen_period(): void
    {
        AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil', 'is_active' => true]);
        $other = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap']);
        $dosen = $this->dosen();

        $this->actingAs($dosen)->post(route('dosen.quizzes.store'), $this->basePayload(['academic_period_id' => $other->id]));

        $this->assertSame($other->id, Quiz::where('dosen_id', $dosen->id)->firstOrFail()->academic_period_id);
    }

    public function test_store_with_explicit_empty_period_keeps_null(): void
    {
        AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil', 'is_active' => true]);
        $dosen = $this->dosen();

        $this->actingAs($dosen)->post(route('dosen.quizzes.store'), $this->basePayload(['academic_period_id' => '']));

        $this->assertNull(Quiz::where('dosen_id', $dosen->id)->firstOrFail()->academic_period_id);
    }

    public function test_store_rejects_unknown_period(): void
    {
        $this->actingAs($this->dosen())
            ->post(route('dosen.quizzes.store'), $this->basePayload(['academic_period_id' => 999]))
            ->assertSessionHasErrors('academic_period_id');

        $this->assertSame(0, Quiz::count());
    }

    public function test_update_changes_period(): void
    {
        $a = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap']);
        $b = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);
        $dosen = $this->dosen();
        $quiz = $this->quiz($dosen, ['academic_period_id' => $a->id]);

        $this->actingAs($dosen)
            ->put(route('dosen.quizzes.update', $quiz), $this->basePayload(['academic_period_id' => $b->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($b->id, $quiz->fresh()->academic_period_id);
    }

    public function test_edit_page_shows_period_select(): void
    {
        $p = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);
        $dosen = $this->dosen();
        $quiz = $this->quiz($dosen, ['academic_period_id' => $p->id]);

        $this->actingAs($dosen)
            ->get(route('dosen.quizzes.edit', $quiz))
            ->assertOk()
            ->assertSee('value="' . $p->id . '" selected', false);
    }

    public function test_index_filters_by_period_and_course_and_only_own_quizzes(): void
    {
        $p1 = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap']);
        $p2 = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);
        $dosen = $this->dosen();
        $other = $this->dosen();
        $mk = Course::create(['dosen_id' => $dosen->id, 'name' => 'Basis Data']);

        $this->quiz($dosen, ['title' => 'KUIS-P1', 'academic_period_id' => $p1->id]);
        $this->quiz($dosen, ['title' => 'KUIS-P2-BD', 'academic_period_id' => $p2->id, 'course_id' => $mk->id]);
        $this->quiz($dosen, ['title' => 'KUIS-P2-UMUM', 'academic_period_id' => $p2->id]);
        $this->quiz($dosen, ['title' => 'KUIS-TANPA']);
        $this->quiz($other, ['title' => 'KUIS-DOSEN-LAIN', 'academic_period_id' => $p2->id]);

        $this->actingAs($dosen)->get(route('dosen.quizzes.index', ['periode' => $p2->id]))
            ->assertOk()
            ->assertSee('KUIS-P2-BD')->assertSee('KUIS-P2-UMUM')
            ->assertDontSee('KUIS-P1')->assertDontSee('KUIS-TANPA')->assertDontSee('KUIS-DOSEN-LAIN');

        $this->actingAs($dosen)->get(route('dosen.quizzes.index', ['periode' => $p2->id, 'mk' => $mk->id]))
            ->assertSee('KUIS-P2-BD')->assertDontSee('KUIS-P2-UMUM');

        $this->actingAs($dosen)->get(route('dosen.quizzes.index', ['periode' => 'none']))
            ->assertSee('KUIS-TANPA')->assertDontSee('KUIS-P1');

        $this->actingAs($dosen)->get(route('dosen.quizzes.index'))
            ->assertSee('KUIS-P1')->assertSee('KUIS-TANPA')->assertSee('2026/2027 Ganjil')
            ->assertDontSee('KUIS-DOSEN-LAIN');
    }
}
