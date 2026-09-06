<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel jobs -- dibutuhkan oleh QUEUE_CONNECTION=database supaya
        // pengiriman email notifikasi kuis baru tidak memblokir/memperlambat
        // request HTTP dosen saat menyimpan pengaturan kuis.
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        Schema::table('quizzes', function (Blueprint $table) {
            // Nullable/kosong = berlaku untuk SEMUA kelas (default aman,
            // kuis lama otomatis tidak berubah perilaku). Diisi array kelas
            // (contoh: ["A","B"]) kalau dosen mau target spesifik.
            $table->json('target_kelas')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('target_kelas');
        });
    }
};
