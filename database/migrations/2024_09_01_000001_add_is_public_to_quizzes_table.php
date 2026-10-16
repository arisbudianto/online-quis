<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            // Default true supaya SEMUA kuis lama yang sudah ada tetap
            // tampil di daftar publik seperti sebelumnya (tidak ada
            // perubahan perilaku untuk kuis yang sudah dibuat).
            $table->boolean('is_public')->default(true)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
