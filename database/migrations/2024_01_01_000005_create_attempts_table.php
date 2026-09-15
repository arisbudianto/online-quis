<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Urutan soal (array id question) yang sudah diacak khusus untuk attempt ini.
            $table->json('question_order');
            $table->dateTime('started_at');
            $table->dateTime('deadline_at');
            $table->dateTime('submitted_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->enum('status', ['in_progress', 'submitted', 'expired'])->default('in_progress');
            $table->timestamps();

            // Mencegah mahasiswa memiliki lebih dari satu attempt "in_progress" pada kuis yang sama
            $table->index(['quiz_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
