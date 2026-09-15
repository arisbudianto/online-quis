<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin Dosen',
            'email' => 'dosen@kampus.ac.id',
            'password' => Hash::make('password'),
            'role' => 'dosen',
        ]);

        User::create([
            'name' => 'Mahasiswa Contoh',
            'nim' => '2024010001',
            'email' => 'mahasiswa@kampus.ac.id',
            'password' => Hash::make('password'),
            'role' => 'mahasiswa',
        ]);
    }
}
