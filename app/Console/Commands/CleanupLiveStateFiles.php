<?php

namespace App\Console\Commands;

use App\Support\LiveStateFile;
use Illuminate\Console\Command;

/** Hapus file status kuis live yang sudah lama (data tetap ada di database). */
class CleanupLiveStateFiles extends Command
{
    protected $signature = 'live:cleanup {--hours=48}';

    protected $description = 'Hapus file JSON status sesi live yang lebih tua dari --hours jam';

    public function handle(LiveStateFile $files): int
    {
        $dir = $files->directory();
        $limit = time() - (int) $this->option('hours') * 3600;
        $deleted = 0;

        foreach (glob($dir . '/*.{json,tmp}', GLOB_BRACE) ?: [] as $path) {
            if (filemtime($path) < $limit && @unlink($path)) {
                $deleted++;
            }
        }

        $this->info("{$deleted} file dihapus.");

        return self::SUCCESS;
    }
}
