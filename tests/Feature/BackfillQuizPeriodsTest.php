<?php

namespace Tests\Feature;

use App\Console\Commands\BackfillQuizPeriods;
use App\Models\AcademicPeriod;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackfillQuizPeriodsTest extends TestCase
{
    use RefreshDatabase;

    private function quiz(?string $start, string $created, array $attrs = []): Quiz
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $quiz = Quiz::create(array_merge([
            'dosen_id' => $dosen->id, 'title' => 'K', 'duration_minutes' => 10, 'max_attempts' => 1,
            'start_time' => $start,
        ], $attrs));
        $quiz->forceFill(['created_at' => $created])->saveQuietly();

        return $quiz;
    }

    /** @dataProvider monthCases */
    public function test_period_mapping(string $date, int $year, string $semester): void
    {
        $this->assertSame([$year, $semester], BackfillQuizPeriods::periodFor(Carbon::parse($date)));
    }

    public static function monthCases(): array
    {
        return [
            'Agustus' => ['2025-08-01', 2025, 'ganjil'],
            'Desember' => ['2025-12-31', 2025, 'ganjil'],
            'Januari' => ['2026-01-15', 2025, 'ganjil'],
            'Februari' => ['2026-02-01', 2025, 'genap'],
            'Juli' => ['2026-07-31', 2025, 'genap'],
        ];
    }

    public function test_preview_changes_nothing(): void
    {
        $q = $this->quiz('2025-09-10 08:00:00', '2025-09-01 00:00:00');

        $this->artisan('periods:backfill-quizzes')
            ->expectsOutputToContain('PRATINJAU: 1 kuis')
            ->assertSuccessful();

        $this->assertNull($q->fresh()->academic_period_id);
        $this->assertSame(0, AcademicPeriod::count());
    }

    public function test_apply_uses_start_time_then_created_at_and_creates_missing_periods(): void
    {
        $existing = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap', 'is_active' => true]);

        $byStart = $this->quiz('2025-10-01 08:00:00', '2026-03-01 00:00:00');   // start_time menang => 2025 Ganjil
        $byCreated = $this->quiz(null, '2026-03-05 00:00:00');                  // => 2025 Genap (sudah ada)

        $this->artisan('periods:backfill-quizzes', ['--apply' => true])->assertSuccessful();

        $ganjil = AcademicPeriod::where(['year_start' => 2025, 'semester' => 'ganjil'])->firstOrFail();
        $this->assertSame($ganjil->id, $byStart->fresh()->academic_period_id);
        $this->assertSame($existing->id, $byCreated->fresh()->academic_period_id);
        $this->assertFalse($ganjil->is_active, 'Periode hasil backfill tidak boleh diaktifkan otomatis.');
        $this->assertSame(2, AcademicPeriod::count());
    }

    public function test_quiz_with_period_is_untouched_and_rerun_is_safe(): void
    {
        $chosen = AcademicPeriod::create(['year_start' => 2024, 'semester' => 'ganjil']);
        $kept = $this->quiz('2025-10-01 08:00:00', '2025-10-01 00:00:00', ['academic_period_id' => $chosen->id]);
        $filled = $this->quiz('2025-10-01 08:00:00', '2025-10-01 00:00:00');

        $this->artisan('periods:backfill-quizzes', ['--apply' => true])->assertSuccessful();
        $first = $filled->fresh()->academic_period_id;

        $this->artisan('periods:backfill-quizzes', ['--apply' => true])
            ->expectsOutputToContain('0 kuis diisi')
            ->assertSuccessful();

        $this->assertSame($chosen->id, $kept->fresh()->academic_period_id);
        $this->assertSame($first, $filled->fresh()->academic_period_id);
    }
}
