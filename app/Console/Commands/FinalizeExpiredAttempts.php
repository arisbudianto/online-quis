<?php

namespace App\Console\Commands;

use App\Models\Attempt;
use App\Services\AttemptFinalizer;
use Illuminate\Console\Command;

/**
 * Menutup attempt yang waktunya sudah habis tetapi masih in_progress,
 * misalnya karena mahasiswa menutup browser sebelum waktu habis.
 * Sebelumnya attempt seperti ini hanya ditutup saat mahasiswa membuka
 * halaman kerjakan lagi, sehingga bisa tertinggal tanpa skor selamanya.
 */
class FinalizeExpiredAttempts extends Command
{
    protected $signature = 'attempts:finalize-expired
        {--grace=2 : Menit jeda setelah deadline sebelum ditutup otomatis}
        {--dry-run : Hanya tampilkan jumlah, tanpa mengubah data}';

    protected $description = 'Nilai dan tutup attempt in_progress yang sudah lewat deadline';

    public function handle(AttemptFinalizer $finalizer): int
    {
        $grace = max(0, (int) $this->option('grace'));
        $cutoff = now()->subMinutes($grace);

        $query = Attempt::where('status', 'in_progress')->where('deadline_at', '<', $cutoff);

        $total = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: {$total} attempt kedaluwarsa akan ditutup.");
            return self::SUCCESS;
        }

        $closed = 0;
        $query->orderBy('id')->chunkById(100, function ($attempts) use ($finalizer, &$closed) {
            foreach ($attempts as $attempt) {
                if ($finalizer->finalize($attempt)) {
                    $closed++;
                }
            }
        });

        $this->info("{$closed} dari {$total} attempt kedaluwarsa ditutup.");

        return self::SUCCESS;
    }
}
