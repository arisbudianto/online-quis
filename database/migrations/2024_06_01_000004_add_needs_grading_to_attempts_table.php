<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // Default false -- attempt lama & kuis pilihan-ganda-murni TIDAK
            // pernah butuh penilaian manual, jadi skor langsung final seperti
            // sekarang. Kolom `status` TIDAK diubah sama sekali (tetap cuma
            // 'in_progress'/'submitted'/'expired' seperti sebelumnya).
            // Catatan: kolom `score` sudah nullable dari migration awal,
            // jadi tidak perlu diubah lagi di sini (menghindari perintah
            // ->change() yang butuh package doctrine/dbal tambahan).
            $table->boolean('needs_grading')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('needs_grading');
        });
    }
};
