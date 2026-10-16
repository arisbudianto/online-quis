<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 3)->unique();
            // Batas bawah nilai angka (inklusif) untuk huruf ini.
            $table->decimal('min_score', 5, 2);
            // Bobot skala 4.
            $table->decimal('weight', 3, 2);
            $table->timestamps();
        });

        // Nilai awal dari tabel konversi pemilik aplikasi (16-10-2026,
        // dikoreksi 19:23): C 60-64, C+ 65-69, D 55-59, E di bawah 55.
        // Angka = batas bawah. Untuk C dan D dipakai bobot versi pertama
        // (2,3 dan 2,0). Admin bisa mengubah dari aplikasi.
        $now = now();
        DB::table('grade_scales')->insert([
            ['letter' => 'A',  'min_score' => 85, 'weight' => 4.0, 'created_at' => $now, 'updated_at' => $now],
            ['letter' => 'A-', 'min_score' => 80, 'weight' => 3.7, 'created_at' => $now, 'updated_at' => $now],
            ['letter' => 'B+', 'min_score' => 75, 'weight' => 3.3, 'created_at' => $now, 'updated_at' => $now],
            ['letter' => 'B',  'min_score' => 70, 'weight' => 3.0, 'created_at' => $now, 'updated_at' => $now],
            ['letter' => 'C+', 'min_score' => 65, 'weight' => 2.7, 'created_at' => $now, 'updated_at' => $now],
            ['letter' => 'C',  'min_score' => 60, 'weight' => 2.3, 'created_at' => $now, 'updated_at' => $now],
            ['letter' => 'D',  'min_score' => 55, 'weight' => 2.0, 'created_at' => $now, 'updated_at' => $now],
            ['letter' => 'E',  'min_score' => 0,  'weight' => 0.0, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
    }
};
