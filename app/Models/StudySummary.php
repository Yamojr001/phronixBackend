<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudySummary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'course_code',
        'files',
        'summary_content',
    ];

    protected $casts = [
        'files' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
