<?php

namespace App\Exports;

use App\Models\Quiz;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ScoresExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Quiz $quiz)
    {
    }

    public function collection()
    {
        return $this->quiz->attempts()
            ->with('user')
            ->where('status', 'submitted')
            ->orderByDesc('score')
            ->get();
    }

    public function headings(): array
    {
        return [
            'NIM', 'Nama', 'Kelas', 'Mata Kuliah', 'Skor', 'Status Penilaian',
            'Waktu Mulai', 'Waktu Submit', 'Durasi Pengerjaan (menit)',
            'Jumlah Tab Switch', 'IP Address',
        ];
    }

    public function map($attempt): array
    {
        $durasi = $attempt->submitted_at
            ? $attempt->started_at->diffInMinutes($attempt->submitted_at)
            : null;

        return [
            $attempt->user->nim,
            $attempt->user->name,
            $attempt->user->kelas,
            $this->quiz->course->name ?? '-',
            $attempt->needs_grading ? '' : $attempt->score,
            $attempt->needs_grading ? 'Menunggu Nilai Esai' : 'Final',
            optional($attempt->started_at)->format('d-m-Y H:i:s'),
            optional($attempt->submitted_at)->format('d-m-Y H:i:s'),
            $durasi,
            $attempt->tab_switch_count,
            $attempt->ip_address,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
