<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Membaca soal dari template xlsx.
 * Kolom (urutan wajib sama dengan template):
 * No | Pertanyaan | Opsi A | Opsi B | Opsi C | Opsi D | Opsi E (opsional) | Jawaban Benar
 *
 * Baris yang tidak lengkap (pertanyaan kosong, salah satu opsi kosong, atau
 * jawaban benar bukan A/B/C/D) otomatis dilewati dan dicatat di $this->skipped.
 */
class QuestionsImport implements ToCollection, WithStartRow
{
    /** @var array<int, array{question:string, options:array}> */
    public array $parsed = [];

    /** @var array<int, string> daftar alasan baris yang dilewati, untuk ditampilkan ke dosen */
    public array $skipped = [];

    // Baris 1 = header, baris 2-3 = contoh di template tapi tetap diproses seperti biasa
    // (dosen bebas menghapus/menimpa contoh tersebut). Data mulai dibaca dari baris 2.
    public function startRow(): int
    {
        return 2;
    }

    public function collection(\Illuminate\Support\Collection $rows)
    {
        foreach ($rows as $i => $row) {
            $excelRowNumber = $i + $this->startRow();

            // Kolom: 0=No, 1=Pertanyaan, 2=Opsi A, 3=Opsi B, 4=Opsi C, 5=Opsi D, 6=Opsi E (opsional), 7=Jawaban Benar
            $question = trim((string) ($row[1] ?? ''));
            $optionA  = trim((string) ($row[2] ?? ''));
            $optionB  = trim((string) ($row[3] ?? ''));
            $optionC  = trim((string) ($row[4] ?? ''));
            $optionD  = trim((string) ($row[5] ?? ''));
            $optionE  = trim((string) ($row[6] ?? ''));
            $correct  = strtoupper(trim((string) ($row[7] ?? '')));

            // Lewati baris yang benar-benar kosong semua (biasanya sisa baris kosong di template)
            if ($question === '' && $optionA === '' && $optionB === '' && $optionC === '' && $optionD === '') {
                continue;
            }

            if ($question === '') {
                $this->skipped[] = "Baris {$excelRowNumber}: kolom Pertanyaan kosong.";
                continue;
            }

            $options = ['A' => $optionA, 'B' => $optionB, 'C' => $optionC, 'D' => $optionD];

            $emptyOptions = array_filter($options, fn ($v) => $v === '');
            if (!empty($emptyOptions)) {
                $missingLabels = implode(', ', array_keys($emptyOptions));
                $this->skipped[] = "Baris {$excelRowNumber}: opsi {$missingLabels} kosong.";
                continue;
            }

            // Opsi E bersifat opsional: kalau diisi, ditambahkan sebagai opsi ke-5.
            $hasOptionE = $optionE !== '';
            $validLetters = $hasOptionE ? ['A', 'B', 'C', 'D', 'E'] : ['A', 'B', 'C', 'D'];

            if (!in_array($correct, $validLetters, true)) {
                $expected = implode('/', $validLetters);
                $this->skipped[] = "Baris {$excelRowNumber}: kolom Jawaban Benar harus diisi {$expected} (ditemukan: \"{$correct}\").";
                continue;
            }

            $parsedOptions = [
                ['text' => $optionA, 'is_correct' => $correct === 'A'],
                ['text' => $optionB, 'is_correct' => $correct === 'B'],
                ['text' => $optionC, 'is_correct' => $correct === 'C'],
                ['text' => $optionD, 'is_correct' => $correct === 'D'],
            ];

            if ($hasOptionE) {
                $parsedOptions[] = ['text' => $optionE, 'is_correct' => $correct === 'E'];
            }

            $this->parsed[] = [
                'question' => $question,
                'options' => $parsedOptions,
            ];
        }
    }
}
