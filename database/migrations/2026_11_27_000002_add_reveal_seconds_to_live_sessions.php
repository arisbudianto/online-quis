<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeda pembahasan sebelum soal berikutnya muncul otomatis (permintaan
 * pemilik 27-11-2026). 0 = manual (dosen/peserta menekan tombol).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('reveal_seconds')->default(5)->after('seconds_per_question');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn('reveal_seconds');
        });
    }
};
