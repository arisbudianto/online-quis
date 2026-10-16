<?php

namespace App\Services;

use App\Models\Attempt;
use Illuminate\Support\Facades\DB;

/**
 * Menilai dan menutup satu attempt. Dipakai oleh submit mahasiswa,
 * halaman kerjakan saat waktu habis, dan command attempts:finalize-expired.
 *
 * Rumus tidak berubah dari versi sebelumnya (dulu private method di
 * Mahasiswa\QuizController):
 * - pilihan_ganda & isian_singkat dinilai otomatis (points 100/0);
 * - esai: points null, menunggu dosen;
 * - skor akhir lewat Attempt::recalculateScoreIfReady().
 */
class AttemptFinalizer
{
    /**
     * @return bool true bila attempt ini yang menutupnya, false bila
     *              attempt sudah tidak in_progress (sudah ditutup proses lain).
     */
    public function finalize(Attempt $attempt): bool
    {
        return DB::transaction(function () use ($attempt) {
            // Kunci baris supaya submit mahasiswa dan command terjadwal
            // tidak menilai attempt yang sama secara bersamaan.
            $locked = Attempt::whereKey($attempt->getKey())->lockForUpdate()->first();

            if (!$locked || $locked->status !== 'in_progress') {
                return false;
            }

            $answers = $locked->answers()->with('question.options')->get();

            foreach ($answers as $answer) {
                $question = $answer->question;

                if ($question->type === 'pilihan_ganda') {
                    $selectedIsCorrect = false;
                    if ($answer->selected_option_id) {
                        $selectedOption = $question->options->firstWhere('id', $answer->selected_option_id);
                        $selectedIsCorrect = (bool) ($selectedOption?->is_correct);
                    }
                    $answer->update([
                        'is_correct' => $selectedIsCorrect,
                        'points' => $selectedIsCorrect ? 100 : 0,
                    ]);
                } elseif ($question->type === 'isian_singkat') {
                    $isCorrect = self::matchesShortAnswer($answer->answer_text, $question->correct_text_answer);
                    $answer->update([
                        'is_correct' => $isCorrect,
                        'points' => $isCorrect ? 100 : 0,
                    ]);
                } else {
                    // esai: belum bisa dinilai otomatis, points tetap null (menunggu dosen)
                    $answer->update(['is_correct' => null, 'points' => null]);
                }
            }

            $locked->update(['status' => 'submitted', 'submitted_at' => now()]);
            $locked->recalculateScoreIfReady();

            // Sinkronkan instance pemanggil.
            $attempt->setRawAttributes($locked->fresh()->getAttributes(), true);

            return true;
        });
    }

    /**
     * Cocokkan jawaban isian singkat: case-insensitive + toleransi spasi
     * berlebih di awal/akhir/tengah.
     */
    public static function matchesShortAnswer(?string $given, ?string $correct): bool
    {
        $normalize = fn ($v) => trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $v)));
        return $normalize($given) !== '' && $normalize($given) === $normalize($correct);
    }
}
