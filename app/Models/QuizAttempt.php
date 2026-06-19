<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id',
        'user_id',
        'started_at',
        'completed_at',
        'score_percentage',
        'xp_earned',
        'is_passed',
    ];

    protected $casts = [
        'is_passed' => 'boolean',
        'score_percentage' => 'float',
        'xp_earned' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(QuizSubmission::class);
    }

    public function getCorrectAnswersCount(): int
    {
        return $this->submissions()->where('is_correct', true)->count();
    }
}
