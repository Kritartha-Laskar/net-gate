<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseName extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'status'];

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'course_id');
    }

    public function questionPapers()
    {
        return $this->hasMany(QuestionPaper::class, 'course_id');
    }
}
