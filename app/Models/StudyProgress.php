<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class StudyProgress extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'study_progress';

    protected $fillable = [
        'user_id',
        'course_id',
        'week_number',
        'day_name',
        'completed_tasks',
        'reading_minutes',
        'reading_completed',
        'reading_completed_at',
        'test_score',
        'test_passed',
        'test_questions',
    ];

    protected $casts = [
        'completed_tasks' => 'array',
        'test_questions' => 'array',
        'test_passed' => 'boolean',
        'reading_minutes' => 'integer',
        'reading_completed' => 'boolean',
        'reading_completed_at' => 'datetime',
    ];

    protected $appends = [
        'reading_progress_percentage',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function getReadingProgressPercentageAttribute(): int
    {
        if ($this->reading_completed) {
            return 100;
        }

        if ((int) $this->reading_minutes <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->reading_minutes / 15) * 100));
    }
}
