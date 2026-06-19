<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSubmission extends Model
{
    protected $fillable = [
        'quiz_attempt_id',
        'quiz_question_id',
        'user_answer',
        'is_correct',
        'ai_feedback',
        'ai_analysis',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'ai_feedback' => 'json',
        'ai_analysis' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
