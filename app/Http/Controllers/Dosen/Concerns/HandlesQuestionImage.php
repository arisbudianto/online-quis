<?php

namespace App\Http\Controllers\Dosen\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Upload gambar soal LANGSUNG ke public/uploads/questions (bukan lewat
 * Laravel Storage disk + symlink). Alasan: beberapa shared hosting cPanel
 * membatasi/menonaktifkan `php artisan storage:link` (symlink), jadi cara
 * ini lebih aman & selalu jalan tanpa langkah tambahan di server.
 *
 * CATATAN KEAMANAN (diperbaiki dari versi sebelumnya): ekstensi file yang
 * disimpan SEKARANG ditentukan dari MIME type file yang sudah divalidasi
 * Laravel (aturan 'image' di controller pemanggil), BUKAN dari nama file
 * asli yang dikirim client (getClientOriginalExtension()). Client bisa
 * mengarang nama file apa saja -- termasuk memberi ekstensi .php pada
 * file yang isinya "kebetulan" juga valid sebagai gambar (teknik
 * "polyglot upload"). Dengan menentukan ekstensi dari MIME type asli
 * (dideteksi server dari isi file, bukan dari nama), file APAPUN yang
 * lolos ke sini dijamin tersimpan dengan salah satu dari 5 ekstensi
 * gambar yang di-whitelist di bawah -- tidak mungkin tersimpan sebagai
 * .php walau nama aslinya "shell.php".
 */
trait HandlesQuestionImage
{
    private const ALLOWED_MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
    ];

    private function storeQuestionImage(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        // getMimeType() membaca isi file yang sebenarnya (lewat fileinfo),
        // BUKAN cuma percaya header/nama yang dikirim client.
        $mime = $file->getMimeType();
        $extension = self::ALLOWED_MIME_TO_EXTENSION[$mime] ?? null;

        // Kalau MIME type-nya bukan salah satu dari 5 tipe gambar yang
        // di-whitelist (termasuk kalau ternyata bukan gambar sama sekali,
        // walau entah bagaimana lolos validasi 'image' di controller),
        // TOLAK -- jangan simpan filenya sama sekali.
        abort_unless($extension !== null, 422, 'Tipe file gambar tidak didukung. Gunakan JPG, PNG, GIF, WEBP, atau BMP.');

        $filename = uniqid('q_', true) . '.' . $extension;
        $file->move(public_path('uploads/questions'), $filename);

        return 'uploads/questions/' . $filename;
    }
}
