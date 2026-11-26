<?php

namespace App\Exports;

use App\Models\LiveSession;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LiveSessionExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private int $rank = 0;

    public function __construct(private LiveSession $session)
    {
    }

    public function collection()
    {
        return $this->session->ranking()->with('user:id,nim,kelas')->get();
    }

    public function headings(): array
    {
        return ['Peringkat', 'Nama', 'NIM', 'Kelas', 'Skor', 'Benar', 'Dijawab', 'Jumlah Soal', 'Beruntun Terbaik', 'Rata-rata Waktu (detik)'];
    }

    public function map($player): array
    {
        $this->rank++;
        $total = $this->session->questionCount();

        return [
            $this->rank,
            $player->nickname,
            $player->user?->nim ?? '-',
            $player->user?->kelas ?? '-',
            $player->score,
            $player->correct_count,
            $player->answered_count,
            $total,
            $player->best_streak,
            $player->answered_count > 0 ? round($player->total_response_ms / max(1, $player->answers()->count()) / 1000, 1) : null,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
