<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationProfile extends Model
{
    use HasFactory;

    protected $appends = [
        'xp_to_next_level',
        'xp_progress_percentage',
    ];

    protected $fillable = [
        'user_id',
        'xp',
        'level',
        'current_streak',
        'longest_streak',
        'last_activity_date',
        'total_focus_minutes',
        'tests_completed',
        'perfect_scores_count',
        'tasks_completed',
    ];

    protected $casts = [
        'xp' => 'integer',
        'level' => 'integer',
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'total_focus_minutes' => 'integer',
        'tests_completed' => 'integer',
        'perfect_scores_count' => 'integer',
        'tasks_completed' => 'integer',
        'last_activity_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function badges()
    {
        return $this->user()->badges();
    }

    public function getXpToNextLevelAttribute(): int
    {
        $nextLevelXp = (int) floor(($this->level) ** 2 * 120);

        return max(0, $nextLevelXp - $this->xp);
    }

    public function getXpProgressPercentageAttribute(): int
    {
        $currentLevelXp = (int) floor(($this->level - 1) ** 2 * 120);
        $nextLevelXp = (int) floor(($this->level) ** 2 * 120);

        if ($nextLevelXp === $currentLevelXp) {
            return 100;
        }

        $xpInLevel = $this->xp - $currentLevelXp;
        $xpNeeded = $nextLevelXp - $currentLevelXp;

        return max(0, min(100, (int) floor(($xpInLevel / $xpNeeded) * 100)));
    }
}
