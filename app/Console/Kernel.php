<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Backup database otomatis tiap hari jam 02:00, simpan 7 backup terakhir.
        $schedule->command('backup:database --keep=7')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        // Proses antrian email (notifikasi kuis baru, dll) -- INI YANG
        // SEBELUMNYA didaftarkan sebagai cron job TERPISAH
        // (`php artisan queue:work --stop-when-empty`). Sekarang digabung
        // ke sini supaya cukup 1 cron job cPanel total untuk semuanya.
        $schedule->command('queue:work --stop-when-empty --max-time=50')
            ->everyMinute()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
