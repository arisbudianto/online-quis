<?php

namespace Tests\Feature;

use App\Models\GradeScale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeScaleTest extends TestCase
{
    use RefreshDatabase;

    /** @dataProvider boundaries */
    public function test_default_conversion(float $score, string $letter, float $weight): void
    {
        $grade = GradeScale::resolve($score);

        $this->assertSame($letter, $grade->letter, "Nilai {$score}");
        $this->assertSame($weight, $grade->weight);
    }

    public static function boundaries(): array
    {
        return [
            [100, 'A', 4.0], [85, 'A', 4.0], [84.99, 'A-', 3.7], [80, 'A-', 3.7],
            [79.5, 'B+', 3.3], [75, 'B+', 3.3], [74, 'B', 3.0], [70, 'B', 3.0],
            [69.99, 'C+', 2.7], [69, 'C+', 2.7], [66, 'C+', 2.7], [65, 'C+', 2.7],   // C+ 65-69
            [64.99, 'C', 2.3], [64, 'C', 2.3], [60, 'C', 2.3],                       // C 60-64
            [59.99, 'D', 2.0], [59, 'D', 2.0], [55, 'D', 2.0],                       // D 55-59
            [54.99, 'E', 0.0], [54, 'E', 0.0], [50, 'E', 0.0], [0, 'E', 0.0],        // E di bawah 55
        ];
    }

    private function payload(array $override = []): array
    {
        $scales = [];
        foreach (GradeScale::ordered() as $s) {
            $scales[$s->id] = [
                'min_score' => $override[$s->letter]['min_score'] ?? $s->min_score,
                'weight' => $override[$s->letter]['weight'] ?? $s->weight,
            ];
        }

        return ['scales' => $scales];
    }

    public function test_admin_can_change_scale_and_conversion_follows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.gradeScales.index'))->assertOk()->assertSee('A-');

        $this->actingAs($admin)
            ->put(route('admin.gradeScales.update'), $this->payload([
                'C' => ['min_score' => 56, 'weight' => 2.0],
                'D' => ['min_score' => 50, 'weight' => 1.0],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('C', GradeScale::resolve(56)->letter);
        $this->assertSame(2.0, GradeScale::resolve(56)->weight);
        $this->assertSame('D', GradeScale::resolve(52)->letter);
        $this->assertSame('E', GradeScale::resolve(49.99)->letter);
    }

    public function test_thresholds_must_strictly_decrease(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.gradeScales.update'), $this->payload(['A-' => ['min_score' => 85]]))
            ->assertSessionHasErrors('scales');

        $this->assertSame(80.0, GradeScale::where('letter', 'A-')->value('min_score') + 0.0);
    }

    public function test_weight_cannot_increase_downwards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.gradeScales.update'), $this->payload(['B' => ['weight' => 3.5]]))
            ->assertSessionHasErrors('scales');
    }

    public function test_lowest_letter_must_start_at_zero(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.gradeScales.update'), $this->payload(['E' => ['min_score' => 10]]))
            ->assertSessionHasErrors('scales');
    }

    public function test_non_admin_cannot_change_scale(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);

        $this->actingAs($dosen)->get(route('admin.gradeScales.index'))->assertForbidden();
        $this->actingAs($dosen)
            ->put(route('admin.gradeScales.update'), $this->payload(['A' => ['min_score' => 90]]))
            ->assertForbidden();

        $this->assertSame('A', GradeScale::resolve(85)->letter);
    }
}
