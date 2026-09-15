<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id', 'dosen_id', 'question_text', 'tag',
        'type', 'correct_text_answer', 'image_path',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function options()
    {
        return $this->hasMany(BankOption::class);
    }

    public function isEssay(): bool
    {
        return $this->type === 'esai';
    }
}
