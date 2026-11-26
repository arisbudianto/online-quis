<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuis interaktif untuk latihan (keputusan pemilik 27-11-2026):
 * - mode "live": dosen memandu soal demi soal di proyektor (seperti Kahoot);
 * - mode "challenge": siswa mengerjakan sendiri dengan papan peringkat
 *   (seperti Quizizz).
 * Peserta cukup scan QR; boleh tanpa akun (nama panggilan). Hasil tidak
 * masuk nilai semester.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 10);                 // live | challenge
            $table->string('code', 6)->unique();        // kode gabung (QR)
            $table->string('state_token', 40)->unique(); // nama file status statis
            $table->string('status', 10)->default('lobby'); // lobby|question|reveal|finished (live), open|finished (challenge)
            $table->unsignedSmallInteger('seconds_per_question')->default(20);
            $table->json('plan');                       // [{q: id, o: [option ids]}] urutan untuk mode live
            $table->integer('current_index')->default(-1);
            $table->unsignedBigInteger('question_started_ms')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['host_id', 'created_at']);
        });

        Schema::create('live_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nickname', 30);
            $table->integer('score')->default(0);
            $table->unsignedSmallInteger('streak')->default(0);
            $table->unsignedSmallInteger('best_streak')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('answered_count')->default(0);
            $table->unsignedBigInteger('total_response_ms')->default(0);
            $table->json('plan')->nullable();           // urutan pribadi (mode tantangan)
            $table->integer('current_index')->default(0);
            $table->unsignedBigInteger('served_ms')->nullable(); // kapan soal saat ini mulai ditampilkan (tantangan)
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['live_session_id', 'user_id']);
            $table->index(['live_session_id', 'score']);
        });

        Schema::create('live_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('live_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('response_ms')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();
            $table->unique(['live_player_id', 'question_id']);
            $table->index(['live_session_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_answers');
        Schema::dropIfExists('live_players');
        Schema::dropIfExists('live_sessions');
    }
};
