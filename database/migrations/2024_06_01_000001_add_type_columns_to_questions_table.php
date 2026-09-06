<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Default 'pilihan_ganda' -- soal yang sudah ada otomatis tetap
            // dianggap pilihan ganda, tidak ada data lama yang berubah perilaku.
            $table->enum('type', ['pilihan_ganda', 'esai', 'isian_singkat'])
                ->default('pilihan_ganda')->after('question_text');
            $table->string('correct_text_answer')->nullable()->after('type');
            $table->string('image_path')->nullable()->after('correct_text_answer');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['type', 'correct_text_answer', 'image_path']);
        });
    }
};
