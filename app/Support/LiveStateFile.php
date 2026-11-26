<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * File JSON statis berisi status sesi live. Web server melayaninya langsung
 * (tanpa PHP), jadi puluhan HP bisa membacanya tiap detik tanpa membebani
 * shared hosting. Nama file = token acak 40 karakter, isinya tidak pernah
 * memuat kunci jawaban sebelum soal ditutup.
 */
class LiveStateFile
{
    public function directory(): string
    {
        return config('live.state_dir') ?: public_path('live');
    }

    public function url(string $token): string
    {
        return rtrim(config('live.state_url') ?: asset('live'), '/') . '/' . $token . '.json';
    }

    public function write(string $token, array $payload): void
    {
        $dir = $this->directory();
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . '/' . $token . '.json';
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @chmod($tmp, 0644);
        rename($tmp, $path); // atomik: pembaca tidak pernah melihat file setengah jadi
    }

    public function read(string $token): ?array
    {
        $path = $this->directory() . '/' . $token . '.json';

        return is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    }

    public function delete(string $token): void
    {
        @unlink($this->directory() . '/' . $token . '.json');
    }
}
