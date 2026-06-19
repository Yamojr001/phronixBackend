<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'creator_id',
        'title',
        'description',
        'category',
        'difficulty_level',
        'is_published',
        'approved_by_admin',
        'status',
        'ai_analysis',
        'total_attempts',
        'success_count',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'approved_by_admin' => 'boolean',
        'ai_analysis' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->where('approved_by_admin', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_published', false);
    }

    public function successRate(): float
    {
        if ($this->total_attempts == 0) return 0;
        return round(($this->success_count / $this->total_attempts) * 100, 2);
    }
}
