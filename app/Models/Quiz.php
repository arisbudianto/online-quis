<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'dosen_id', 'course_id', 'title', 'description',
        'duration_minutes', 'start_time', 'end_time', 'max_attempts',
        'shuffle_questions', 'shuffle_options', 'questions_to_show',
        'is_published', 'target_kelas', 'is_public',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'shuffle_questions' => 'boolean',
        'shuffle_options' => 'boolean',
        'is_published' => 'boolean',
        'is_public' => 'boolean',
        'target_kelas' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Quiz $quiz) {
            if (empty($quiz->code)) {
                $quiz->code = static::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('code', $code)->exists());
        return $code;
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }

    public function isOpenNow(): bool
    {
        $now = now();
        if ($this->start_time && $now->lt($this->start_time)) return false;
        if ($this->end_time && $now->gt($this->end_time)) return false;
        return $this->is_published;
    }

    /**
     * Ambil mahasiswa yang jadi target notifikasi kuis ini. Kalau
     * target_kelas kosong/null, berarti SEMUA mahasiswa (default aman,
     * kuis lama yang tidak pernah diisi kelas targetnya tetap jalan
     * seperti sebelumnya -- dilihat semua orang).
     */
    public function targetStudents()
    {
        return User::where('role', 'mahasiswa')
            ->when(!empty($this->target_kelas), function ($q) {
                $q->whereIn('kelas', $this->target_kelas);
            })
            ->get();
    }
}
