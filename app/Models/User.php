<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * PERBAIKAN PENTING: sebelumnya class ini TIDAK "implements MustVerifyEmail"
 * sama sekali (interface-nya cuma di-import tapi tidak dipasang), padahal
 * middleware `verified` yang membungkus hampir semua route di aplikasi ini
 * mengecek `instanceof MustVerifyEmail` untuk tahu apakah harus menegakkan
 * verifikasi email. Akibatnya middleware itu diam-diam TIDAK PERNAH aktif
 * sejak awal, dan email verifikasi tidak pernah terkirim ke mahasiswa
 * manapun. Ditemukan lewat automated test (ProfileTest), diperbaiki di sini.
 *
 * WAJIB jalankan perintah backfill di PANDUAN.txt SEBELUM/BERSAMAAN update
 * ini aktif -- supaya akun yang SUDAH ADA (yang email_verified_at-nya NULL
 * karena memang belum pernah diminta verifikasi) tidak mendadak terkunci.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, MustVerifyEmailTrait;

    /** Kelas yang berlaku di prodi (keputusan pemilik aplikasi, 16-10-2026). */
    public const KELAS = ['A', 'B', 'C'];

    protected $fillable = [
        'name',
        'nim',
        'kelas',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /** Tahun angkatan dari awalan NIM, null bila tidak dikenali. */
    public function getAngkatanAttribute(): ?string
    {
        return \App\Support\Angkatan::detect($this->nim);
    }

    public function scopeAngkatan($query, ?string $angkatan)
    {
        return \App\Support\Angkatan::apply($query, $angkatan, $query->getModel()->qualifyColumn('nim'));
    }

    public function isDosen(): bool
    {
        return $this->role === 'dosen' || $this->role === 'admin';
    }

    public function isMahasiswa(): bool
    {
        return $this->role === 'mahasiswa';
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'dosen_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'dosen_id');
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }
}
