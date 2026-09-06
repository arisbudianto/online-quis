<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

/**
 * Mem-parsing file .docx berisi soal pilihan ganda menjadi array terstruktur.
 *
 * Format docx yang diharapkan (per soal), dipisahkan baris kosong antar soal:
 *
 *   1. Ibu kota Indonesia adalah?
 *   A. Bandung
 *   B. Jakarta
 *   C. Surabaya
 *   D. Medan
 *   Jawaban: B
 *
 * Aturan:
 * - Nomor soal diikuti titik lalu teks soal (mis. "1.", "12.").
 * - Opsi diawali huruf A-D/a-d diikuti titik atau tanda kurung, misal "A." atau "A)".
 * - Baris terakhir tiap soal wajib "Jawaban: <HURUF>" (case-insensitive, boleh "Kunci:" juga).
 */
class DocxQuestionParser
{
    /**
     * @return array<int, array{question: string, options: array<int, array{text: string, is_correct: bool}>}>
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File tidak ditemukan: {$filePath}");
        }

        $phpWord = IOFactory::load($filePath);
        $lines = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text = $this->extractTextFromElement($element);
                if ($text !== null) {
                    $text = trim($text);
                    // Baris kosong tetap dipertahankan sebagai pemisah antar soal
                    $lines[] = $text;
                }
            }
        }

        return $this->parseLines($lines);
    }

    private function extractTextFromElement($element): ?string
    {
        if (method_exists($element, 'getText')) {
            return $element->getText();
        }

        if (method_exists($element, 'getElements')) {
            $buffer = '';
            foreach ($element->getElements() as $child) {
                $childText = $this->extractTextFromElement($child);
                if ($childText !== null) {
                    $buffer .= $childText;
                }
            }
            return $buffer;
        }

        return null;
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, array{question: string, options: array<int, array{text: string, is_correct: bool}>}>
     */
    private function parseLines(array $lines): array
    {
        $questions = [];
        $current = null;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                continue;
            }

            // Deteksi awal soal baru: "1. teks soal" / "12) teks soal"
            if (preg_match('/^\d+[\.\)]\s*(.+)$/u', $line, $m)) {
                if ($current !== null) {
                    $this->pushIfValid($questions, $current);
                }
                $current = [
                    'question' => trim($m[1]),
                    'options' => [],
                ];
                continue;
            }

            // Deteksi opsi jawaban: "A. teks" / "a) teks"
            if (preg_match('/^([A-Da-d])[\.\)]\s*(.+)$/u', $line, $m) && $current !== null) {
                $current['options'][] = [
                    'label' => strtoupper($m[1]),
                    'text' => trim($m[2]),
                    'is_correct' => false,
                ];
                continue;
            }

            // Deteksi baris kunci jawaban: "Jawaban: B" / "Kunci: B"
            if (preg_match('/^(Jawaban|Kunci)\s*[:\-]\s*([A-Da-d])/iu', $line, $m) && $current !== null) {
                $correctLabel = strtoupper($m[2]);
                foreach ($current['options'] as &$opt) {
                    $opt['is_correct'] = ($opt['label'] === $correctLabel);
                }
                unset($opt);
                continue;
            }

            // Baris lanjutan teks soal (jika soal ditulis multi-baris sebelum opsi muncul)
            if ($current !== null && empty($current['options'])) {
                $current['question'] .= ' ' . $line;
            }
        }

        if ($current !== null) {
            $this->pushIfValid($questions, $current);
        }

        return $questions;
    }

    private function pushIfValid(array &$questions, array $item): void
    {
        $hasCorrect = collect($item['options'])->contains('is_correct', true);
        if (empty($item['question']) || count($item['options']) < 2 || !$hasCorrect) {
            // Soal tidak lengkap/rusak dilewati; sebaiknya dicatat sebagai warning di controller pemanggil.
            return;
        }

        $questions[] = [
            'question' => $item['question'],
            'options' => array_map(fn ($o) => [
                'text' => $o['text'],
                'is_correct' => $o['is_correct'],
            ], $item['options']),
        ];
    }
}
