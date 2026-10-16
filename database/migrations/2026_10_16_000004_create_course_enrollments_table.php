<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daftar peserta resmi satu mata kuliah pada satu periode.
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'academic_period_id', 'user_id'], 'course_enrollments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};
