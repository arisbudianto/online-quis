<?php

namespace App\Imports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Membaca & memvalidasi daftar mahasiswa dari template xlsx.
 * Kolom (urutan wajib sama dengan template): Nama | NIM | Kelas | Email
 *
 * Class ini HANYA memvalidasi & mengumpulkan baris yang valid ke $this->parsed
 * (belum menyimpan ke database -- itu tugas controller, supaya proses simpan
 * bisa dibungkus transaction & mengirim notifikasi email per user).
 */
class StudentsImport implements ToCollection, WithStartRow
{
    /** @var array<int, array{name:string, nim:string, kelas:string, email:string}> */
    public array $parsed = [];

    /** @var array<int, string> alasan tiap baris yang dilewati, untuk ditampilkan ke dosen */
    public array $skipped = [];

    /** @var array<int, int> mahasiswa yang sudah punya akun (NIM dan email sama), untuk dihubungkan ke dosen */
    public array $existingIds = [];

    /**
     * @param array<int, string> $allowedKelas kelas milik pengimpor; kosong = kelas bebas (admin)
     * @param User|null $teacher dosen pengimpor; bila ada, siswa lama dengan NIM+email sama dihubungkan
     */
    public function __construct(private array $allowedKelas = [], private ?User $teacher = null)
    {
    }

    // Baris 1 = header, baris 2-3 di template adalah CONTOH tapi tetap diproses
    // seperti biasa (dosen bebas menghapus/menimpa contoh itu). Data mulai
    // dibaca dari baris 2.
    public function startRow(): int
    {
        return 2;
    }

    public function collection(\Illuminate\Support\Collection $rows)
    {
        // Lacak NIM/email yang sudah muncul DI DALAM FILE INI, supaya baris
        // duplikat di file yang sama tidak dua-duanya lolos (yang kedua & seterusnya dilewati).
        $seenNim = [];
        $seenEmail = [];

        foreach ($rows as $i => $row) {
            $excelRowNumber = $i + $this->startRow();

            $name = trim((string) ($row[0] ?? ''));
            $nim = $this->normalizeNim((string) ($row[1] ?? ''));
            $kelas = trim((string) ($row[2] ?? ''));
            $email = strtolower(trim((string) ($row[3] ?? '')));

            // Lewati baris yang benar-benar kosong semua (sisa baris kosong di template)
            if ($name === '' && $nim === '' && $kelas === '' && $email === '') {
                continue;
            }

            if ($name === '') {
                $this->skipped[] = "Baris {$excelRowNumber}: kolom Nama kosong.";
                continue;
            }

            if ($nim === '') {
                $this->skipped[] = "Baris {$excelRowNumber}: kolom NIM kosong.";
                continue;
            }

            $kelas = $this->matchKelas($kelas);
            if ($kelas === null) {
                $this->skipped[] = ($this->teacher && $this->allowedKelas === [])
                    ? "Baris {$excelRowNumber}: daftar kelas Anda masih kosong. Tambahkan kelas di menu Kelas Saya dulu."
                    : "Baris {$excelRowNumber}: kolom Kelas harus salah satu dari "
                    . ($this->allowedKelas ? implode('/', $this->allowedKelas) : 'kelas yang tidak kosong')
                    . " (ditemukan: \"" . trim((string) ($row[2] ?? '')) . "\").";
                continue;
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->skipped[] = "Baris {$excelRowNumber}: email \"{$email}\" tidak valid.";
                continue;
            }

            if (isset($seenNim[$nim])) {
                $this->skipped[] = "Baris {$excelRowNumber}: NIM \"{$nim}\" duplikat dengan baris {$seenNim[$nim]} di file ini.";
                continue;
            }

            if (isset($seenEmail[$email])) {
                $this->skipped[] = "Baris {$excelRowNumber}: email \"{$email}\" duplikat dengan baris {$seenEmail[$email]} di file ini.";
                continue;
            }

            $existing = User::where('role', 'mahasiswa')->where('nim', $nim)->first();
            if ($existing && $this->teacher && strcasecmp($existing->email, $email) === 0) {
                $this->existingIds[] = $existing->id;
                $seenNim[$nim] = $excelRowNumber;
                $seenEmail[$email] = $excelRowNumber;
                continue;
            }

            if (User::where('nim', $nim)->exists()) {
                $this->skipped[] = "Baris {$excelRowNumber}: NIM \"{$nim}\" sudah terdaftar di sistem.";
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $this->skipped[] = "Baris {$excelRowNumber}: email \"{$email}\" sudah terdaftar di sistem.";
                continue;
            }

            $seenNim[$nim] = $excelRowNumber;
            $seenEmail[$email] = $excelRowNumber;

            $this->parsed[] = [
                'name' => $name,
                'nim' => $nim,
                'kelas' => $kelas,
                'email' => $email,
            ];
        }
    }

    /** Cocokkan kelas tanpa membedakan huruf besar/kecil; kembalikan ejaan resmi. */
    private function matchKelas(string $kelas): ?string
    {
        if ($kelas === '') {
            return null;
        }

        if ($this->allowedKelas === []) {
            return $this->teacher ? null : strtoupper($kelas);
        }

        foreach ($this->allowedKelas as $k) {
            if (strcasecmp($k, $kelas) === 0) {
                return $k;
            }
        }

        return null;
    }

    /**
     * Excel kerap mengubah NIM murni angka jadi number (menghilangkan angka 0
     * di depan, atau menambah ".0" di belakang). Normalisasi dasar di sini,
     * tapi cara paling aman tetap: format kolom NIM sebagai "Text" di Excel
     * sebelum diisi.
     */
    private function normalizeNim(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^\d+\.0$/', $value)) {
            $value = substr($value, 0, -2);
        }

        return $value;
    }
}
