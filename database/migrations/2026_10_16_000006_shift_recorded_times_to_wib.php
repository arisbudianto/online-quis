<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zona waktu aplikasi pindah dari UTC ke Asia/Jakarta (16-10-2026).
 *
 * Sebelumnya semua waktu yang DICATAT SISTEM (now()) ditulis dalam jam UTC,
 * dan MySQL menyimpannya apa adanya. Setelah aplikasi memakai WIB, nilai
 * lama itu harus digeser +7 jam supaya riwayat tetap benar dan deadline
 * attempt yang sedang berjalan tidak tiba-tiba terlihat lewat 7 jam.
 *
 * TIDAK digeser:
 * - quizzes.start_time / end_time: diketik dosen dalam jam WIB sejak awal.
 * - failed_jobs.failed_at: diisi MySQL (CURRENT_TIMESTAMP) dalam jam server (WIB).
 *
 * Satu UPDATE per tabel yang menyetel semua kolomnya sekaligus, supaya
 * ON UPDATE CURRENT_TIMESTAMP (kalau ada) tidak menimpa updated_at.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'academic_periods' => ['created_at', 'updated_at'],
        'attempts' => ['created_at', 'updated_at', 'started_at', 'deadline_at', 'submitted_at'],
        'attempt_answers' => ['created_at', 'updated_at'],
        'bank_options' => ['created_at', 'updated_at'],
        'bank_questions' => ['created_at', 'updated_at'],
        'courses' => ['created_at', 'updated_at'],
        'course_enrollments' => ['created_at', 'updated_at'],
        'grade_scales' => ['created_at', 'updated_at'],
        'notifications' => ['created_at', 'updated_at', 'read_at'],
        'options' => ['created_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'personal_access_tokens' => ['created_at', 'updated_at', 'last_used_at', 'expires_at'],
        'questions' => ['created_at', 'updated_at'],
        'quizzes' => ['created_at', 'updated_at'],
        'users' => ['created_at', 'updated_at', 'email_verified_at'],
    ];

    public function up(): void
    {
        $this->shift(7);
    }

    public function down(): void
    {
        $this->shift(-7);
    }

    private function shift(int $hours): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (self::COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
            if ($columns === []) {
                continue;
            }

            $sets = array_map(fn ($c) => $this->quote($c) . ' = ' . $this->expression($driver, $c, $hours), $columns);

            DB::statement('UPDATE ' . $this->quote($table) . ' SET ' . implode(', ', $sets));
        }
    }

    private function expression(string $driver, string $column, int $hours): string
    {
        $col = $this->quote($column);

        if ($driver === 'sqlite') {
            $sign = $hours >= 0 ? '+' : '-';
            return "datetime({$col}, '{$sign}" . abs($hours) . " hours')";
        }

        // mysql / mariadb. NULL tetap NULL.
        return "DATE_ADD({$col}, INTERVAL {$hours} HOUR)";
    }

    private function quote(string $identifier): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? '"' . $identifier . '"'
            : '`' . $identifier . '`';
    }
};
