<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['dosen_id', 'name'];

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function bankQuestions()
    {
        return $this->hasMany(BankQuestion::class);
    }
}
