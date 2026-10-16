<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 'user_id', 'question_order', 'started_at', 'deadline_at',
        'submitted_at', 'score', 'status', 'tab_switch_count',
        'browser_session_id', 'device_token', 'ip_address', 'user_agent', 'needs_grading',
    ];

    protected $casts = [
        'question_order' => 'array',
        'started_at' => 'datetime',
        'deadline_at' => 'datetime',
        'submitted_at' => 'datetime',
        'needs_grading' => 'boolean',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function isExpired(): bool
    {
        return now()->gt($this->deadline_at);
    }

    /**
     * Bandingkan device_token (cookie berumur panjang, independen dari
     * session Laravel) -- BUKAN session()->getId() seperti sebelumnya.
     * session()->getId() bisa berubah sendiri karena alasan teknis biasa
     * (garbage collection, dsb), yang dulu menyebabkan mahasiswa salah
     * dianggap ganti device padahal masih di browser yang sama, dan
     * attempt-nya nyangkut "in_progress" selamanya.
     *
     * Kalau attempt lama belum punya device_token (dibuat sebelum kolom
     * ini ada), anggap dulu cocok (null-safe) supaya tidak mendadak
     * mengunci attempt yang sedang berjalan di tengah rilis fitur ini.
     */
    public function isFromDifferentDevice(?string $currentDeviceToken): bool
    {
        if ($this->status !== 'in_progress' || empty($this->device_token)) {
            return false;
        }

        return $this->device_token !== $currentDeviceToken;
    }

    /**
     * Hitung ulang skor akhir dari rata-rata "points" (0-100) tiap soal, lalu
     * simpan sebagai skor final -- HANYA kalau semua soal esai di attempt ini
     * sudah dinilai dosen (tidak ada answers.points yang masih null untuk
     * soal bertipe esai). Dipanggil baik saat submit (untuk kuis tanpa esai,
     * langsung final seperti sekarang) maupun setelah dosen selesai menilai
     * esai terakhir di suatu attempt.
     */
    public function recalculateScoreIfReady(): void
    {
        $answers = $this->answers()->with('question')->get();

        $stillWaiting = $answers->contains(fn ($a) => $a->question->isEssay() && is_null($a->points));

        if ($stillWaiting) {
            $this->update(['needs_grading' => true, 'score' => null]);
            return;
        }

        $avg = $answers->avg('points') ?? 0;

        $this->update([
            'needs_grading' => false,
            'score' => round((float) $avg, 2),
        ]);
    }
}
