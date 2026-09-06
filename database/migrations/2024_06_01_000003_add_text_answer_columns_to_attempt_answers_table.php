<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->text('answer_text')->nullable()->after('selected_option_id');
            // points: skor 0-100 PER SOAL. Untuk pilihan_ganda & isian_singkat
            // diisi otomatis (100/0) saat submit -- identik dengan cara hitung
            // skor yang sudah ada sekarang. Untuk esai, diisi manual oleh dosen
            // (null = belum dinilai).
            $table->decimal('points', 5, 2)->nullable()->after('is_correct');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropColumn(['answer_text', 'points']);
        });
    }
};
