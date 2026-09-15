<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    private function backupDir(): string
    {
        return storage_path('app/backups');
    }

    public function index()
    {
        $dir = $this->backupDir();

        $backups = collect();

        if (File::exists($dir)) {
            $backups = collect(File::files($dir))
                ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql'))
                ->sortByDesc(fn ($f) => $f->getMTime())
                ->map(fn ($f) => [
                    'filename' => $f->getFilename(),
                    'size' => $f->getSize(),
                    'modified' => $f->getMTime(),
                ])
                ->values();
        }

        return view('admin.backups.index', compact('backups'));
    }

    /**
     * Jalankan backup manual dari UI (bukan cuma nunggu jadwal otomatis
     * jam 2 pagi). Dijalankan synchronous -- bisa makan waktu beberapa
     * detik tergantung ukuran database, ditampilkan pesan tunggu di view.
     */
    public function runNow(Request $request)
    {
        $exitCode = Artisan::call('backup:database');
        $output = Artisan::output();

        if ($exitCode === 0) {
            return back()->with('status', 'Backup manual berhasil dijalankan.');
        }

        return back()->withErrors(['backup' => 'Backup gagal: ' . trim($output)]);
    }

    public function download(Request $request, string $filename)
    {
        // Validasi ketat: cuma boleh nama file .sql, cegah path traversal (../)
        abort_unless(preg_match('/^backup_[\d\-_]+\.sql$/', $filename), 404);

        $path = $this->backupDir() . '/' . $filename;

        abort_unless(File::exists($path), 404);

        return response()->download($path);
    }
}
