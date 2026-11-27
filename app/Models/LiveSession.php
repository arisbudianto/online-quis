<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LiveSession extends Model
{
    public const MODE_LIVE = 'live';
    public const MODE_CHALLENGE = 'challenge';

    public const SECONDS_OPTIONS = [10, 20, 30, 45, 60, 90];

    /** Jeda pembahasan sebelum lanjut otomatis; 0 = manual. */
    public const REVEAL_OPTIONS = [3, 5, 8, 10, 0];

    protected $fillable = [
        'quiz_id', 'host_id', 'mode', 'code', 'state_token', 'status',
        'seconds_per_question', 'reveal_seconds', 'plan', 'current_index', 'question_started_ms',
        'version', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'plan' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'question_started_ms' => 'integer',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function players()
    {
        return $this->hasMany(LivePlayer::class);
    }

    public function answers()
    {
        return $this->hasMany(LiveAnswer::class);
    }

    public function isLive(): bool
    {
        return $this->mode === self::MODE_LIVE;
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function questionCount(): int
    {
        return count($this->plan ?? []);
    }

    public function modeLabel(): string
    {
        return $this->isLive() ? 'Live' : 'Tantangan';
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

    public static function newStateToken(): string
    {
        return Str::lower(Str::random(40));
    }

    /** Peringkat: skor tertinggi, lalu total waktu jawab tercepat, lalu yang bergabung lebih dulu. */
    public function ranking()
    {
        return $this->players()->orderByDesc('score')->orderBy('total_response_ms')->orderBy('id');
    }
}
