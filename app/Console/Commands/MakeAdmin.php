<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    protected $signature = 'buat:admin';
    protected $description = 'Buat akun admin baru secara interaktif (HANYA bisa dijalankan lewat terminal, tidak ada form web untuk ini demi keamanan)';

    public function handle(): int
    {
        $this->info('=== Buat Akun Admin Baru ===');

        $name = $this->ask('Nama lengkap');
        $email = $this->ask('Email');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $password = $this->secret('Password (kosongkan untuk generate otomatis)');
        if (empty($password)) {
            $password = Str::random(12);
            $this->warn("Password digenerate otomatis: {$password}");
            $this->warn('CATAT password ini sekarang, tidak akan ditampilkan lagi.');
        }

        if (!$this->confirm("Buat admin \"{$name}\" ({$email})?", true)) {
            $this->info('Dibatalkan.');
            return self::SUCCESS;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info("Akun admin \"{$email}\" berhasil dibuat.");

        return self::SUCCESS;
    }
}
