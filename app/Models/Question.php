<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 'question_text', 'order',
        'type', 'correct_text_answer', 'image_path',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options()
    {
        return $this->hasMany(Option::class);
    }

    public function correctOption()
    {
        return $this->hasOne(Option::class)->where('is_correct', true);
    }

    public function isEssay(): bool
    {
        return $this->type === 'esai';
    }

    public function isShortAnswer(): bool
    {
        return $this->type === 'isian_singkat';
    }

    public function isMultipleChoice(): bool
    {
        return $this->type === 'pilihan_ganda';
    }
}
