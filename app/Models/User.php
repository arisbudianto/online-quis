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

    /**
     * Kelas bawaan prodi asal (keputusan pemilik aplikasi, 16-10-2026).
     * Sejak pendaftaran mandiri dosen/guru, daftar kelas yang berlaku diambil
     * dari teacher_classes milik masing-masing dosen; konstanta ini hanya
     * dipakai sebagai nilai awal dosen lama.
     */
    public const KELAS = ['A', 'B', 'C'];

    protected $fillable = [
        'name',
        'nim',
        'kelas',
        'email',
        'password',
        'role',
        'institution',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
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

    /** Dosen/guru yang dipilih siswa ini. */
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'student_teacher', 'student_id', 'teacher_id')->withTimestamps();
    }

    /** Siswa yang memilih dosen ini. */
    public function students()
    {
        return $this->belongsToMany(User::class, 'student_teacher', 'teacher_id', 'student_id')->withTimestamps();
    }

    public function teacherClasses()
    {
        return $this->hasMany(TeacherClass::class, 'teacher_id');
    }

    /** Dosen yang mendaftar sendiri baru aktif setelah disetujui admin. */
    public function isPendingApproval(): bool
    {
        return $this->role === 'dosen' && $this->approved_at === null;
    }

    public function scopeApprovedTeachers($query)
    {
        return $query->where('role', 'dosen')->whereNotNull('approved_at');
    }

    /**
     * Daftar kelas yang berlaku untuk pengguna ini:
     * - dosen: kelas miliknya sendiri;
     * - admin: gabungan kelas semua dosen;
     * - siswa: gabungan kelas dosen-dosen yang dipilihnya.
     *
     * @return array<int, string>
     */
    public function kelasOptions(): array
    {
        $query = TeacherClass::query();

        if ($this->role === 'dosen') {
            $query->where('teacher_id', $this->id);
        } elseif ($this->role === 'mahasiswa') {
            $query->whereIn('teacher_id', $this->teachers()->select('users.id'));
        }

        return $query->distinct()->orderBy('name')->pluck('name')->all();
    }

    /** Gabungan kelas dari beberapa dosen. @param array<int, int> $teacherIds */
    public static function kelasOptionsFor(array $teacherIds): array
    {
        return TeacherClass::whereIn('teacher_id', $teacherIds)->distinct()->orderBy('name')->pluck('name')->all();
    }

    /**
     * Mahasiswa yang boleh dilihat dan dikelola $viewer:
     * admin melihat semua, dosen hanya siswa yang memilihnya.
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        $query->where('role', 'mahasiswa');

        if ($viewer->role !== 'admin') {
            // Relasi ke tabel users sendiri: Laravel memberi alias pada tabel
            // users di subquery, jadi saring lewat kolom pivot.
            $query->whereHas('teachers', fn ($q) => $q->where('student_teacher.teacher_id', $viewer->id));
        }

        return $query;
    }

    public function canManageStudent(User $student): bool
    {
        if ($student->role !== 'mahasiswa') {
            return false;
        }

        return $this->role === 'admin'
            || ($this->role === 'dosen' && $student->teachers()->where('users.id', $this->id)->exists());
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
