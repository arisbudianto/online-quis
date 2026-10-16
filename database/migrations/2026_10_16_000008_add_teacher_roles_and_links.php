<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran mandiri dosen/guru dan siswa (keputusan pemilik 16-10-2026):
 * - Dosen yang mendaftar sendiri menunggu persetujuan admin (approved_at).
 * - Siswa memilih satu atau lebih dosen (student_teacher).
 * - Setiap dosen punya daftar kelas sendiri (teacher_classes).
 *
 * Data lama: dosen/admin yang sudah ada dianggap disetujui, setiap dosen
 * lama mendapat kelas A, B, C, dan semua mahasiswa lama dihubungkan ke
 * semua dosen lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('role');
            $table->string('institution')->nullable()->after('approved_at');
        });

        Schema::create('teacher_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 30);
            $table->timestamps();
            $table->unique(['teacher_id', 'name']);
        });

        Schema::create('student_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'teacher_id']);
            $table->index('teacher_id');
        });

        $now = now();

        DB::table('users')->whereIn('role', ['dosen', 'admin'])->update(['approved_at' => $now]);

        $teacherIds = DB::table('users')->where('role', 'dosen')->pluck('id');

        foreach ($teacherIds as $teacherId) {
            DB::table('teacher_classes')->insert(array_map(fn ($k) => [
                'teacher_id' => $teacherId, 'name' => $k, 'created_at' => $now, 'updated_at' => $now,
            ], ['A', 'B', 'C']));
        }

        DB::table('users')->where('role', 'mahasiswa')->orderBy('id')->select('id')
            ->chunk(200, function ($students) use ($teacherIds, $now) {
                $rows = [];
                foreach ($students as $s) {
                    foreach ($teacherIds as $t) {
                        $rows[] = ['student_id' => $s->id, 'teacher_id' => $t, 'created_at' => $now, 'updated_at' => $now];
                    }
                }
                if ($rows) {
                    DB::table('student_teacher')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_teacher');
        Schema::dropIfExists('teacher_classes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'institution']);
        });
    }
};
