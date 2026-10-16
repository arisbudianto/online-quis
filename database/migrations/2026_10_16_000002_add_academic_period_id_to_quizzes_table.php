<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            // Nullable: kuis lama belum punya periode sampai backfill dijalankan.
            // restrictOnDelete: periode yang sudah dipakai kuis tidak bisa dihapus.
            $table->foreignId('academic_period_id')->nullable()->after('course_id')
                ->constrained('academic_periods')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_period_id');
        });
    }
};
