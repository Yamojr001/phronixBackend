<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class BadgeDefinition extends Model
{
    protected $table = 'badge_definitions';
    protected $fillable = [
        'slug',
        'name',
        'tier',
        'tier_level',
        'description',
        'icon',
        'color_class',
        'xp_required',
        'is_active',
    ];

    protected $casts = [
        'xp_required' => 'integer',
        'tier_level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class, 'slug', 'slug');
    }

    // Get all badge definitions ordered by XP requirement
    public static function getProgression()
    {
        return self::where('is_active', true)
            ->orderBy('xp_required')
            ->get();
    }

    // Get next badge for a user based on current XP
    public static function getNextBadgeForXp(int $currentXp)
    {
        return self::where('is_active', true)
            ->where('xp_required', '>', $currentXp)
            ->orderBy('xp_required')
            ->first();
    }

    // Calculate progression percentage
    public function getProgressionPercentageAttribute()
    {
        $badges = self::where('is_active', true)->orderBy('xp_required')->get();
        $currentIndex = $badges->search(fn($b) => $b->id === $this->id);
        $total = $badges->count();
        
        return $total > 0 ? round(($currentIndex + 1) / $total * 100) : 0;
    }
}
