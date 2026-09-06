<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder tambahan untuk mengisi 5 mata kuliah yang diampu dosen.
 * Cara pakai (jalankan sekali saja via terminal cPanel):
 *   php artisan db:seed --class=CourseSeeder --force
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $dosen = User::where('role', 'dosen')->first();

        if (!$dosen) {
            $this->command->warn('Tidak ada user dengan role dosen. Jalankan DatabaseSeeder dulu, atau buat akun dosen terlebih dahulu.');
            return;
        }

        $mataKuliah = [
            'Teknologi Pembelajaran',
            'Ilmu Pendidikan',
            'Landasan Keilmuan PTK',
            'Strategi Belajar Mengajar',
            'Perencanaan Pembelajaran',
        ];

        foreach ($mataKuliah as $nama) {
            Course::firstOrCreate([
                'dosen_id' => $dosen->id,
                'name' => $nama,
            ]);
        }

        $this->command->info('5 mata kuliah berhasil ditambahkan untuk dosen: ' . $dosen->name);
    }
}
