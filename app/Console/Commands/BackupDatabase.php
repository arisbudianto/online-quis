<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BackupFailedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=7 : Jumlah backup terakhir yang disimpan}';
    protected $description = 'Backup database MySQL via mysqldump, simpan di storage (BUKAN public), hapus backup lama sesuai retensi';

    /**
     * Sengaja disimpan di storage/app/backups (BUKAN public/) supaya TIDAK
     * bisa diakses lewat URL publik -- folder storage/app tidak ter-expose
     * ke web sama sekali, beda dengan public/ yang bisa diakses browser.
     */
    private string $backupDir = 'backups';

    public function handle(): int
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $fullPath = storage_path('app/' . $this->backupDir);

        if (!File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        $filename = 'backup_' . now()->format('Y-m-d_His') . '.sql';
        $filePath = $fullPath . '/' . $filename;

        $connection = config('database.connections.mysql');

        $command = [
            'mysqldump',
            '-h', $connection['host'],
            '-P', (string) $connection['port'],
            '-u', $connection['username'],
            '--password=' . $connection['password'],
            $connection['database'],
        ];

        $process = new Process($command);
        $process->setTimeout(300); // maks 5 menit, cukup untuk database ukuran kampus

        try {
            $process->mustRun();
            File::put($filePath, $process->getOutput());

            if (!File::exists($filePath) || File::size($filePath) === 0) {
                throw new \RuntimeException('File backup kosong/gagal ditulis.');
            }

            $this->info("Backup berhasil: {$filename} (" . $this->formatSize(File::size($filePath)) . ")");

            $this->pruneOldBackups($fullPath, (int) $this->option('keep'));

            return self::SUCCESS;
        } catch (ProcessFailedException|\Throwable $e) {
            $this->error('Backup GAGAL: ' . $e->getMessage());
            report($e);

            // Hapus file parsial kalau sempat ter-buat tapi rusak
            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            $this->notifyAdmins($e->getMessage());

            return self::FAILURE;
        }
    }

    private function pruneOldBackups(string $dir, int $keep): void
    {
        $files = collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        $toDelete = $files->slice($keep);

        foreach ($toDelete as $file) {
            File::delete($file->getPathname());
            $this->info('Backup lama dihapus: ' . $file->getFilename());
        }
    }

    private function notifyAdmins(string $errorMessage): void
    {
        $admins = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        try {
            Notification::send($admins, new BackupFailedNotification($errorMessage));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
