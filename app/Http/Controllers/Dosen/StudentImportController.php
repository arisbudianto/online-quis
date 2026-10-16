<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use App\Models\User;
use App\Notifications\AccountCredentialsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportController extends Controller
{
    public function form()
    {
        return view('dosen.students.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $sendEmail = $request->boolean('send_email', true);

        $importer = $request->user();
        $import = new StudentsImport($importer->kelasOptions(), $importer->role === 'dosen' ? $importer : null);

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal membaca file xlsx: ' . $e->getMessage()]);
        }

        if (empty($import->parsed) && empty($import->existingIds)) {
            $message = 'Tidak ada baris mahasiswa yang valid untuk diimpor.';
            if (!empty($import->skipped)) {
                $message .= ' Detail: ' . implode(' | ', array_slice($import->skipped, 0, 8));
            }
            return back()->withErrors(['file' => $message]);
        }

        $createdUsers = [];

        DB::transaction(function () use ($import, $importer, &$createdUsers) {
            foreach ($import->parsed as $row) {
                $plainPassword = Str::random(8);

                $user = User::create([
                    'name' => $row['name'],
                    'nim' => $row['nim'],
                    'kelas' => $row['kelas'],
                    'email' => $row['email'],
                    'password' => Hash::make($plainPassword),
                    'role' => 'mahasiswa',
                ]);

                if ($importer->role === 'dosen') {
                    $user->teachers()->syncWithoutDetaching([$importer->id]);
                }

                $createdUsers[] = ['user' => $user, 'password' => $plainPassword];
            }

            // Siswa yang sudah punya akun (NIM dan email sama) cukup
            // dihubungkan ke dosen pengimpor.
            if ($importer->role === 'dosen') {
                foreach ($import->existingIds as $id) {
                    $importer->students()->syncWithoutDetaching([$id]);
                }
            }
        });

        $emailFailures = 0;

        if ($sendEmail) {
            foreach ($createdUsers as $item) {
                try {
                    $item['user']->notify(new AccountCredentialsNotification($item['password']));
                } catch (\Throwable $e) {
                    report($e);
                    $emailFailures++;
                }
            }
        }

        $status = count($createdUsers) . ' akun mahasiswa berhasil dibuat.';
        if (!empty($import->existingIds)) {
            $status .= ' ' . count($import->existingIds) . ' mahasiswa yang sudah punya akun dihubungkan ke Anda.';
        }

        if ($sendEmail) {
            $status .= ' Email kredensial dikirim ke semuanya' . ($emailFailures > 0 ? " (kecuali {$emailFailures} yang gagal terkirim, cek storage/logs/laravel.log)." : '.');
        } else {
            $status .= ' Email TIDAK dikirim (sesuai pilihan Anda).';
        }

        if (!empty($import->skipped)) {
            $status .= ' ' . count($import->skipped) . ' baris dilewati: '
                . implode(' | ', array_slice($import->skipped, 0, 8))
                . (count($import->skipped) > 8 ? ' (dan lainnya)' : '');
        }

        return redirect()->route('dosen.students.index')->with('status', $status);
    }

    public function index(Request $request)
    {
        $angkatan = $request->query('angkatan');
        $angkatan = \App\Support\Angkatan::isValid($angkatan) ? $angkatan : null;
        $kelas    = $request->query('kelas');

        $viewer = $request->user();
        $kelasOptions = $viewer->kelasOptions();

        // Dosen hanya melihat siswa yang memilihnya; admin melihat semua.
        $students = User::visibleTo($viewer)
            ->with('teachers:id,name')
            ->angkatan($angkatan)
            ->when($kelas, fn ($q) => $q->where('kelas', $kelas))
            ->orderBy('kelas')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('dosen.students.index', compact('students', 'angkatan', 'kelas', 'kelasOptions'));
    }
}
