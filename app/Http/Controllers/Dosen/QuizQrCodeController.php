<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QuizQrCodeController extends Controller
{
    /**
     * True kalau ekstensi Imagick tersedia di server -- dipakai untuk
     * generate PNG. Kalau tidak ada (umum di sebagian shared hosting),
     * otomatis fallback ke SVG (tidak butuh ekstensi tambahan apapun).
     */
    private function canUsePng(): bool
    {
        return class_exists(\Imagick::class);
    }

    private function joinUrl(Quiz $quiz): string
    {
        return route('mahasiswa.quizzes.join', ['code' => $quiz->code]);
    }

    /**
     * Tampilkan QR code inline (dipakai sebagai src <img> di halaman Kelola Kuis).
     * Ukuran 400px -- cukup jelas di-scan dari jarak wajar (misal ditayangkan
     * di layar proyektor kelas), tapi tetap ringan untuk dimuat di browser.
     */
    public function show(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $url = $this->joinUrl($quiz);

        if ($this->canUsePng()) {
            $image = QrCode::format('png')->size(400)->margin(1)->generate($url);
            return response($image)->header('Content-Type', 'image/png');
        }

        $image = QrCode::format('svg')->size(400)->margin(1)->generate($url);
        return response($image)->header('Content-Type', 'image/svg+xml');
    }

    /**
     * Unduh QR code sebagai file (PNG kalau Imagick tersedia, SVG kalau tidak)
     * dengan ukuran lebih besar (800px) supaya kualitas cetak/tempel tetap tajam.
     */
    public function download(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $url = $this->joinUrl($quiz);
        $filename = 'qrcode-' . str($quiz->code)->lower();

        if ($this->canUsePng()) {
            $image = QrCode::format('png')->size(800)->margin(2)->generate($url);
            return response($image)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '.png"');
        }

        $image = QrCode::format('svg')->size(800)->margin(2)->generate($url);
        return response($image)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '.svg"');
    }
}
