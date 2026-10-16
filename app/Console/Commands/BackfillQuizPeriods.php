<?php

namespace App\Console\Commands;

use App\Models\AcademicPeriod;
use App\Models\Quiz;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi academic_period_id untuk kuis lama yang belum punya periode.
 * Default hanya pratinjau. Data baru diubah dengan --apply.
 *
 * Tanggal acuan: start_time, atau created_at bila start_time kosong.
 * ASUMSI (keputusan D1, belum dikonfirmasi):
 *   Agustus sampai Januari = Ganjil, Februari sampai Juli = Genap.
 * Periode yang belum ada dibuat otomatis (tidak diaktifkan).
 * Kuis yang sudah punya periode tidak disentuh, jadi aman dijalankan ulang.
 */
class BackfillQuizPeriods extends Command
{
    protected $signature = 'periods:backfill-quizzes {--apply : Benar-benar mengubah data}';

    protected $description = 'Isi periode akademik kuis lama dari start_time/created_at (default: pratinjau)';

    /** @return array{0:int,1:string} [year_start, semester] */
    public static function periodFor(CarbonInterface $date): array
    {
        $month = (int) $date->month;
        $year = (int) $date->year;

        if ($month >= 8) {
            return [$year, 'ganjil'];
        }
        if ($month === 1) {
            return [$year - 1, 'ganjil'];
        }

        return [$year - 1, 'genap'];
    }

    public function handle(): int
    {
        $quizzes = Quiz::whereNull('academic_period_id')->get(['id', 'start_time', 'created_at']);

        $plan = [];      // "year|semester" => [quiz ids]
        $undated = [];

        foreach ($quizzes as $quiz) {
            $date = $quiz->start_time ?? $quiz->created_at;
            if (!$date) {
                $undated[] = $quiz->id;
                continue;
            }
            [$year, $semester] = self::periodFor($date);
            $plan["$year|$semester"][] = $quiz->id;
        }

        ksort($plan);

        $existing = AcademicPeriod::all()->keyBy(fn ($p) => $p->year_start . '|' . $p->semester);

        $rows = [];
        foreach ($plan as $key => $ids) {
            [$year, $semester] = explode('|', $key);
            $label = (new AcademicPeriod(['year_start' => (int) $year, 'semester' => $semester]))->label;
            $rows[] = [$label, count($ids), $existing->has($key) ? 'sudah ada' : 'akan dibuat'];
        }

        $this->info(($this->option('apply') ? 'APPLY' : 'PRATINJAU') . ': ' . $quizzes->count() . ' kuis belum punya periode.');
        $this->table(['Periode', 'Jumlah kuis', 'Periode di DB'], $rows);

        if ($undated) {
            $this->warn(count($undated) . ' kuis tanpa start_time maupun created_at dilewati: ' . implode(', ', $undated));
        }

        if (!$this->option('apply')) {
            $this->line('Tidak ada data yang diubah. Jalankan dengan --apply untuk menerapkan.');
            return self::SUCCESS;
        }

        $updated = 0;
        DB::transaction(function () use ($plan, &$updated) {
            foreach ($plan as $key => $ids) {
                [$year, $semester] = explode('|', $key);
                $period = AcademicPeriod::firstOrCreate(['year_start' => (int) $year, 'semester' => $semester]);

                // whereNull lagi: jangan menimpa kuis yang diisi dosen
                // di antara pratinjau dan apply.
                $updated += Quiz::whereIn('id', $ids)
                    ->whereNull('academic_period_id')
                    ->update(['academic_period_id' => $period->id]);
            }
        });

        $this->info("{$updated} kuis diisi periodenya.");

        return self::SUCCESS;
    }
}
