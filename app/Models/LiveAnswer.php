<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveAnswer extends Model
{
    protected $fillable = [
        'live_session_id', 'live_player_id', 'question_id', 'option_id',
        'is_correct', 'response_ms', 'points',
    ];

    protected $casts = ['is_correct' => 'boolean'];

    public function player()
    {
        return $this->belongsTo(LivePlayer::class, 'live_player_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
