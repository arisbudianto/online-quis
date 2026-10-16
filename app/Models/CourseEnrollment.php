<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    protected $fillable = ['course_id', 'academic_period_id', 'user_id'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
