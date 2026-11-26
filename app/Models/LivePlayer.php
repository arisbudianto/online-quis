<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LivePlayer extends Model
{
    protected $fillable = [
        'live_session_id', 'user_id', 'nickname', 'score', 'streak', 'best_streak',
        'correct_count', 'answered_count', 'total_response_ms', 'plan', 'current_index',
        'served_ms', 'finished_at',
    ];

    protected $casts = [
        'plan' => 'array',
        'finished_at' => 'datetime',
        'served_ms' => 'integer',
    ];

    public function session()
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(LiveAnswer::class);
    }
}
