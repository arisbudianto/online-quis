<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttemptAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id', 'question_id', 'option_order',
        'selected_option_id', 'is_correct',
        'answer_text', 'points',
    ];

    protected $casts = [
        'option_order' => 'array',
        'is_correct' => 'boolean',
        'points' => 'decimal:2',
    ];

    public function attempt()
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function selectedOption()
    {
        return $this->belongsTo(Option::class, 'selected_option_id');
    }
}
