<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PastQuestion extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'school',
        'exam_name',
        'course_code',
        'course_title',
        'year',
        'file_path',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
