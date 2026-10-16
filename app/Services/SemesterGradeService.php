<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\GradeScale;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rekap nilai satu mata kuliah dalam satu periode akademik untuk satu dosen.
 *
 * Aturan (keputusan pemilik aplikasi 16-10-2026, kecuali yang ditandai):
 * - Kuis yang dihitung: milik dosen ini, course & periode yang dipilih.
 * - Peserta: daftar peserta resmi (course_enrollments) untuk course+periode.
 *   Mahasiswa yang mengerjakan tapi tidak terdaftar dilaporkan terpisah
 *   di 'unenrolled', tidak masuk rekap.
 * - Nilai per kuis: skor tertinggi dari attempt submitted yang sudah final
 *   (default sementara, belum dikonfirmasi).
 * - Ada attempt submitted yang masih menunggu nilai esai dan belum ada
 *   attempt final lain => sel "pending", nilai akhir belum final, tanpa huruf.
 * - Tidak mengerjakan => sel "none", dihitung MISSING_SCORE (default 0,
 *   belum dikonfirmasi).
 * - Nilai akhir = rata-rata sama rata semua kuis, 2 desimal, lalu
 *   dikonversi ke huruf lewat GradeScale.
 */
class SemesterGradeService
{
    public const CELL_FINAL = 'final';
    public const CELL_PENDING = 'pending';
    public const CELL_NONE = 'none';

    /** Skor yang dipakai untuk kuis yang tidak dikerjakan. */
    public const MISSING_SCORE = 0.0;

    /**
     * @return array{
     *   quizzes: Collection<int, Quiz>,
     *   rows: Collection<int, array{student: User, cells: array, final: ?float, letter: ?string, weight: ?float, complete: bool}>,
     *   kelasOptions: array<int, string>,
     *   enrolledCount: int,
     *   unenrolled: Collection<int, User>
     * }
     */
    public function build(User $dosen, Course $course, AcademicPeriod $period, ?string $kelas = null): array
    {
        $quizzes = Quiz::where('dosen_id', $dosen->id)
            ->where('course_id', $course->id)
            ->where('academic_period_id', $period->id)
            ->orderByRaw('start_time IS NULL')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get(['id', 'title', 'start_time']);

        $students = User::whereIn('id', CourseEnrollment::where('course_id', $course->id)
                ->where('academic_period_id', $period->id)
                ->select('user_id'))
            ->get(['id', 'name', 'nim', 'kelas']);

        $kelasOptions = $students->pluck('kelas')->filter()->unique()->sort()->values()->all();

        $attempts = $quizzes->isEmpty()
            ? collect()
            : Attempt::whereIn('quiz_id', $quizzes->pluck('id'))
                ->where('status', 'submitted')
                ->get(['id', 'quiz_id', 'user_id', 'score', 'needs_grading']);

        $byUser = $attempts->groupBy('user_id');

        $enrolledIds = $students->pluck('id')->flip();
        $unenrolled = User::whereIn('id', $byUser->keys()->reject(fn ($id) => $enrolledIds->has($id))->values())
            ->orderBy('kelas')->orderBy('nim')
            ->get(['id', 'name', 'nim', 'kelas']);

        $scales = GradeScale::ordered();

        $rows = $students
            ->when($kelas !== null && $kelas !== '', fn ($c) => $c->filter(fn ($s) => $s->kelas === $kelas))
            ->map(function (User $student) use ($quizzes, $scales, $byUser) {
                $byQuiz = $byUser->get($student->id, collect())->groupBy('quiz_id');

                $cells = [];
                foreach ($quizzes as $quiz) {
                    $cells[$quiz->id] = $this->cell($byQuiz->get($quiz->id, collect()));
                }

                $complete = collect($cells)->every(fn ($c) => $c['status'] !== self::CELL_PENDING);

                $final = ($complete && $quizzes->isNotEmpty())
                    ? round(collect($cells)->avg(fn ($c) => $c['score'] ?? self::MISSING_SCORE), 2)
                    : null;

                $grade = $final !== null ? GradeScale::resolve($final, $scales) : null;

                return [
                    'student' => $student,
                    'cells' => $cells,
                    'final' => $final,
                    'letter' => $grade?->letter,
                    'weight' => $grade?->weight,
                    'complete' => $complete,
                ];
            })
            ->sortBy(fn ($r) => [$r['student']->kelas ?? '', $r['student']->nim ?? '', $r['student']->name])
            ->values();

        return [
            'quizzes' => $quizzes,
            'rows' => $rows,
            'kelasOptions' => $kelasOptions,
            'enrolledCount' => $students->count(),
            'unenrolled' => $unenrolled,
        ];
    }

    /**
     * @param Collection<int, Attempt> $attempts attempt submitted mahasiswa untuk satu kuis
     * @return array{status: string, score: ?float, attempts: int}
     */
    private function cell(Collection $attempts): array
    {
        if ($attempts->isEmpty()) {
            return ['status' => self::CELL_NONE, 'score' => null, 'attempts' => 0];
        }

        $final = $attempts->where('needs_grading', false)->whereNotNull('score');

        if ($final->isEmpty()) {
            return ['status' => self::CELL_PENDING, 'score' => null, 'attempts' => $attempts->count()];
        }

        return [
            'status' => self::CELL_FINAL,
            'score' => round((float) $final->max('score'), 2),
            'attempts' => $attempts->count(),
        ];
    }
}
