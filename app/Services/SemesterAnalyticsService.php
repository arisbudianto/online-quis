<?php

namespace App\Services;

use App\Models\GradeScale;
use Illuminate\Support\Collection;

/**
 * Statistik deskriptif lintas kuis untuk satu mata kuliah dalam satu
 * periode. Sumber datanya hasil SemesterGradeService::build(), sehingga
 * angka di halaman analitik selalu cocok dengan halaman Rekap Nilai:
 * peserta resmi, pengerjaan pertama, tidak mengerjakan = 0.
 */
class SemesterAnalyticsService
{
    /**
     * @param array $recap hasil SemesterGradeService::build()
     */
    public function build(array $recap): array
    {
        $quizzes = $recap['quizzes'];
        $rows = $recap['rows'];
        $scales = GradeScale::ordered();

        $completeRows = $rows->where('complete', true);
        $finals = $completeRows->pluck('final')->filter(fn ($v) => $v !== null)->values();

        // Batas "perlu perhatian" = batas bawah huruf C pada tabel konversi.
        $cThreshold = optional($scales->firstWhere('letter', 'C'))->min_score;

        return [
            'summary' => [
                'participants' => $rows->count(),
                'quizzes' => $quizzes->count(),
                'final_count' => $finals->count(),
                'pending' => $rows->count() - $completeRows->count(),
                'stats' => self::describe($finals),
            ],
            'letters' => $this->letterDistribution($completeRows, $scales),
            'perQuiz' => $this->perQuiz($quizzes, $rows),
            'perKelas' => $this->perKelas($rows, $cThreshold),
            'attention' => $this->attention($rows, $quizzes, $cThreshold),
            'cThreshold' => $cThreshold,
        ];
    }

    /**
     * Statistik deskriptif. Simpangan baku = sampel (n - 1). Kuartil memakai
     * interpolasi linear (metode yang sama dengan Excel QUARTILE.INC).
     *
     * @return array{n:int, mean:?float, median:?float, sd:?float, min:?float, max:?float, q1:?float, q3:?float}
     */
    public static function describe(Collection $values): array
    {
        $v = $values->map(fn ($x) => (float) $x)->sort()->values();
        $n = $v->count();

        if ($n === 0) {
            return ['n' => 0, 'mean' => null, 'median' => null, 'sd' => null, 'min' => null, 'max' => null, 'q1' => null, 'q3' => null];
        }

        $mean = $v->avg();
        $sd = $n > 1
            ? sqrt($v->reduce(fn ($c, $x) => $c + ($x - $mean) ** 2, 0.0) / ($n - 1))
            : null;

        return [
            'n' => $n,
            'mean' => round($mean, 2),
            'median' => round(self::quantile($v, 0.5), 2),
            'sd' => $sd === null ? null : round($sd, 2),
            'min' => round($v->first(), 2),
            'max' => round($v->last(), 2),
            'q1' => round(self::quantile($v, 0.25), 2),
            'q3' => round(self::quantile($v, 0.75), 2),
        ];
    }

    /** @param Collection<int, float> $sorted nilai sudah terurut naik */
    private static function quantile(Collection $sorted, float $p): float
    {
        $n = $sorted->count();
        $pos = ($n - 1) * $p;
        $lower = (int) floor($pos);
        $upper = (int) ceil($pos);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($pos - $lower);
    }

    private function letterDistribution(Collection $completeRows, Collection $scales): array
    {
        $total = $completeRows->count();

        return $scales->map(function ($scale) use ($completeRows, $total) {
            $count = $completeRows->where('letter', $scale->letter)->count();

            return [
                'letter' => $scale->letter,
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    private function perQuiz(Collection $quizzes, Collection $rows): array
    {
        $enrolled = $rows->count();

        return $quizzes->map(function ($quiz) use ($rows, $enrolled) {
            $cells = $rows->map(fn ($r) => $r['cells'][$quiz->id]);
            $done = $cells->where('status', '!=', SemesterGradeService::CELL_NONE);
            $scores = $cells->where('status', SemesterGradeService::CELL_FINAL)->pluck('score');

            return [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'start_time' => $quiz->start_time,
                'attempted' => $done->count(),
                'participation' => $enrolled > 0 ? round($done->count() / $enrolled * 100, 1) : 0.0,
                'pending' => $cells->where('status', SemesterGradeService::CELL_PENDING)->count(),
                'stats' => self::describe($scores),
            ];
        })->values()->all();
    }

    private function perKelas(Collection $rows, ?float $cThreshold): array
    {
        return $rows->groupBy(fn ($r) => $r['student']->kelas ?? '-')
            ->sortKeys()
            ->map(function (Collection $group, $kelas) use ($cThreshold) {
                $finals = $group->where('complete', true)->pluck('final')->filter(fn ($v) => $v !== null);
                $cellCount = $group->sum(fn ($r) => count($r['cells']));
                $doneCount = $group->sum(fn ($r) => collect($r['cells'])->where('status', '!=', SemesterGradeService::CELL_NONE)->count());

                return [
                    'kelas' => $kelas,
                    'participants' => $group->count(),
                    'stats' => self::describe($finals->values()),
                    'participation' => $cellCount > 0 ? round($doneCount / $cellCount * 100, 1) : 0.0,
                    'below_c' => $cThreshold === null ? 0 : $finals->filter(fn ($f) => $f < $cThreshold)->count(),
                ];
            })->values()->all();
    }

    private function attention(Collection $rows, Collection $quizzes, ?float $cThreshold): array
    {
        return $rows->map(function ($r) use ($cThreshold) {
            $missed = collect($r['cells'])->where('status', SemesterGradeService::CELL_NONE)->count();
            $belowC = $r['complete'] && $cThreshold !== null && $r['final'] < $cThreshold;

            return [
                'student' => $r['student'],
                'final' => $r['final'],
                'letter' => $r['letter'],
                'missed' => $missed,
                'below_c' => $belowC,
            ];
        })
            ->filter(fn ($a) => $a['below_c'] || $a['missed'] > 0)
            ->sortBy(fn ($a) => [$a['final'] ?? 999, -$a['missed']])
            ->values()
            ->all();
    }
}
