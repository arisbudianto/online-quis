<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aturan pemilik aplikasi (16-10-2026): mahasiswa hanya boleh mengerjakan
 * satu kali. Kuis lama yang batasnya lebih dari 1 diseragamkan menjadi 1.
 * Nilai lama tidak disimpan: down() tidak bisa mengembalikannya (pakai
 * backup database bila perlu).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('quizzes')->where('max_attempts', '>', 1)->update(['max_attempts' => 1]);
    }

    public function down(): void
    {
        // Tidak bisa dibalik tanpa data lama.
    }
};
