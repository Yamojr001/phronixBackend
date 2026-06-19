<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class CourseContent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_code',
        'content',
        'school',
        'year',
        'department',
    ];
}
