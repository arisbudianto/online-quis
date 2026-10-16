<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherClass extends Model
{
    protected $fillable = ['teacher_id', 'name'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
