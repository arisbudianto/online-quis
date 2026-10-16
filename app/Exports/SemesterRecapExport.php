<?php

namespace App\Exports;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Services\SemesterGradeService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SemesterRecapExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private Course $course,
        private AcademicPeriod $period,
        private array $recap,
    ) {
    }

    public function title(): string
    {
        return 'Rekap';
    }

    public function headings(): array
    {
        return array_merge(
            ['NIM', 'Nama', 'Kelas'],
            $this->recap['quizzes']->pluck('title')->all(),
            ['Nilai Akhir', 'Huruf', 'Bobot', 'Keterangan'],
        );
    }

    public function array(): array
    {
        return $this->recap['rows']->map(function ($row) {
            $cells = [];
            foreach ($this->recap['quizzes'] as $quiz) {
                $cell = $row['cells'][$quiz->id];
                $cells[] = match ($cell['status']) {
                    SemesterGradeService::CELL_FINAL => $cell['score'],
                    SemesterGradeService::CELL_PENDING => 'Menunggu nilai esai',
                    default => 'Tidak mengerjakan',
                };
            }

            return array_merge(
                [$row['student']->nim, $row['student']->name, $row['student']->kelas],
                $cells,
                [
                    $row['final'],
                    $row['letter'],
                    $row['weight'],
                    $row['complete'] ? 'Final' : 'Belum final (esai belum dinilai)',
                ],
            );
        })->all();
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
