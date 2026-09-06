<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankOption extends Model
{
    use HasFactory;

    protected $fillable = ['bank_question_id', 'option_text', 'is_correct', 'order'];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function bankQuestion()
    {
        return $this->belongsTo(BankQuestion::class);
    }
}
