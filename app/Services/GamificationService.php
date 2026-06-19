<?php

namespace App\Services;

use App\Models\GamificationProfile;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\BadgeDefinition;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GamificationService
{
    public function awardXp(User $user, int $xp): GamificationProfile
    {
        $profile = $this->touchActivity($user);

        return $this->grantXp($profile, max(0, $xp));
    }

    public function ensureProfile(User $user): GamificationProfile
    {
        return GamificationProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'xp' => 0,
                'level' => 1,
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_focus_minutes' => 0,
                'tests_completed' => 0,
                'perfect_scores_count' => 0,
                'tasks_completed' => 0,
            ]
        );
    }

    public function recordTaskCompletion(User $user): GamificationProfile
    {
        $profile = $this->touchActivity($user);
        $profile->increment('tasks_completed');

        return $this->grantXp($profile, 12);
    }

    public function recordStudyMinutes(User $user, int $minutes): GamificationProfile
    {
        $minutes = max(0, min($minutes, 240));
        $profile = $this->touchActivity($user);

        $previousTotalMinutes = (int) $profile->total_focus_minutes;

        if ($minutes > 0) {
            $profile->increment('total_focus_minutes', $minutes);
        }

        // Award XP cumulatively so frequent small syncs (e.g. 1 minute) still progress.
        $newTotalMinutes = $previousTotalMinutes + $minutes;
        $previousFiveMinuteBlocks = intdiv($previousTotalMinutes, 5);
        $newFiveMinuteBlocks = intdiv($newTotalMinutes, 5);
        $xp = max(0, ($newFiveMinuteBlocks - $previousFiveMinuteBlocks) * 3);

        return $this->grantXp($profile, $xp);
    }

    public function recordTestResult(User $user, float $score): GamificationProfile
    {
        $profile = $this->touchActivity($user);

        $baseXp = 35;
        $bonusXp = (int) floor(max(0, min(100, $score)) / 5);
        $profile->increment('tests_completed');

        if ($score >= 90) {
            $profile->increment('perfect_scores_count');
            $bonusXp += 20;
        }

        if ($score >= 70) {
            $bonusXp += 10;
        }

        return $this->grantXp($profile, $baseXp + $bonusXp);
    }

    public function getDailyChallenges(User $user): array
    {
        $today = Carbon::today();
        $profile = $this->ensureProfile($user);

        // Count both formal tests and Study Room daily test submissions.
        $todayTests = $user->tests()
            ->where(function ($query) use ($today) {
                $query->whereDate('taken_date', $today)
                    ->orWhereDate('created_at', $today);
            })
            ->count();

        $todayStudyRoomTests = $user->studyProgress()
            ->whereDate('updated_at', $today)
            ->whereNotNull('test_score')
            ->count();

        $todayAssessmentCount = $todayTests + $todayStudyRoomTests;

        $todayTasks = $user->studyProgress()->whereDate('updated_at', $today)->get()
            ->sum(fn ($progress) => count($progress->completed_tasks ?? []));

        $focusToday = (int) $profile->total_focus_minutes;

        return [
            [
                'id' => 'focus_30',
                'title' => 'Focus For 30 Minutes',
                'target' => 30,
                'progress' => min($focusToday, 30),
                'unit' => 'minutes',
                'completed' => $focusToday >= 30,
            ],
            [
                'id' => 'task_3',
                'title' => 'Complete 3 Daily Tasks',
                'target' => 3,
                'progress' => min($todayTasks, 3),
                'unit' => 'tasks',
                'completed' => $todayTasks >= 3,
            ],
            [
                'id' => 'test_1',
                'title' => 'Finish 1 Test',
                'target' => 1,
                'progress' => min($todayAssessmentCount, 1),
                'unit' => 'test',
                'completed' => $todayAssessmentCount >= 1,
            ],
        ];
    }

    public function getUserBadges(User $user): Collection
    {
        return $user->badges()->latest('awarded_at')->get();
    }

    public function getBadgeDefinitions(): Collection
    {
        return BadgeDefinition::query()
            ->where('is_active', true)
            ->orderBy('xp_required')
            ->get();
    }

    public function syncBadges(User $user): Collection
    {
        $profile = $this->ensureProfile($user);
        $this->awardBadges($profile);

        return $this->getUserBadges($user);
    }

    public function getNextBadge(User $user): ?BadgeDefinition
    {
        $profile = $this->ensureProfile($user);

        return BadgeDefinition::query()
            ->where('is_active', true)
            ->where('xp_required', '>', $profile->xp)
            ->orderBy('xp_required')
            ->first();
    }

    public function touchActivity(User $user): GamificationProfile
    {
        $profile = $this->ensureProfile($user);
        $today = Carbon::today();

        if ($profile->last_activity_date === null) {
            $profile->current_streak = 1;
            $profile->longest_streak = max($profile->longest_streak, 1);
            $profile->last_activity_date = $today;
            $profile->save();

            return $profile;
        }

        $lastActivity = Carbon::parse($profile->last_activity_date)->startOfDay();
        if ($lastActivity->equalTo($today)) {
            return $profile;
        }

        if ($lastActivity->copy()->addDay()->equalTo($today)) {
            $profile->current_streak += 1;
        } else {
            $profile->current_streak = 1;
        }

        $profile->longest_streak = max($profile->longest_streak, $profile->current_streak);
        $profile->last_activity_date = $today;
        $profile->save();

        return $profile;
    }

    private function grantXp(GamificationProfile $profile, int $xp): GamificationProfile
    {
        if ($xp > 0) {
            $profile->xp += $xp;
        }

        $profile->level = $this->calculateLevel($profile->xp);
        $profile->save();

        $this->awardBadges($profile);

        return $profile->refresh();
    }

    private function calculateLevel(int $xp): int
    {
        return max(1, (int) floor(sqrt($xp / 120)) + 1);
    }

    private function awardBadges(GamificationProfile $profile): void
    {
        // Get all badge definitions ordered by XP requirement
        $badgeDefinitions = BadgeDefinition::where('is_active', true)
            ->orderBy('xp_required')
            ->get();

        foreach ($badgeDefinitions as $badgeDef) {
            // Award badge if user has reached the XP requirement
            if ($profile->xp >= $badgeDef->xp_required) {
                UserBadge::firstOrCreate(
                    [
                        'user_id' => $profile->user_id,
                        'slug' => $badgeDef->slug,
                    ],
                    [
                        'name' => $badgeDef->name,
                        'icon' => $badgeDef->icon,
                        'description' => $badgeDef->description,
                        'awarded_at' => now(),
                    ]
                );
            }
        }

        // Also award special achievement badges based on other conditions
        $this->awardAchievementBadges($profile);
    }

    /**
     * Award special achievement badges based on non-XP criteria
     */
    private function awardAchievementBadges(GamificationProfile $profile): void
    {
        $achievementRules = [
            [
                'slug' => 'streak_7',
                'name' => 'Week Warrior',
                'description' => 'Keep a 7-day learning streak.',
                'icon' => 'fa-fire',
                'color_class' => 'bg-red-100 text-red-600',
                'condition' => $profile->longest_streak >= 7,
            ],
            [
                'slug' => 'focus_10h',
                'name' => 'Deep Focus Master',
                'description' => 'Accumulate 10 hours of focused study.',
                'icon' => 'fa-clock',
                'color_class' => 'bg-indigo-100 text-indigo-600',
                'condition' => $profile->total_focus_minutes >= 600,
            ],
            [
                'slug' => 'ace_5',
                'name' => 'Test Ace',
                'description' => 'Score 90%+ in 5 tests.',
                'icon' => 'fa-trophy',
                'color_class' => 'bg-yellow-100 text-yellow-600',
                'condition' => $profile->perfect_scores_count >= 5,
            ],
            [
                'slug' => 'streak_30',
                'name' => 'Unstoppable',
                'description' => 'Maintain a 30-day learning streak.',
                'icon' => 'fa-bolt',
                'color_class' => 'bg-green-100 text-green-600',
                'condition' => $profile->longest_streak >= 30,
            ],
            [
                'slug' => 'focus_50h',
                'name' => 'Endurance Champion',
                'description' => 'Accumulate 50 hours of focused study.',
                'icon' => 'fa-hourglass-end',
                'color_class' => 'bg-cyan-100 text-cyan-600',
                'condition' => $profile->total_focus_minutes >= 3000,
            ],
            [
                'slug' => 'perfect_10',
                'name' => 'Perfect Scholar',
                'description' => 'Achieve 10 perfect test scores (90%+).',
                'icon' => 'fa-star',
                'color_class' => 'bg-pink-100 text-pink-600',
                'condition' => $profile->perfect_scores_count >= 10,
            ],
            [
                'slug' => 'tasks_100',
                'name' => 'Task Master',
                'description' => 'Complete 100 daily tasks.',
                'icon' => 'fa-tasks',
                'color_class' => 'bg-teal-100 text-teal-600',
                'condition' => $profile->tasks_completed >= 100,
            ],
        ];

        foreach ($achievementRules as $achievement) {
            if (!$achievement['condition']) {
                continue;
            }

            UserBadge::firstOrCreate(
                [
                    'user_id' => $profile->user_id,
                    'slug' => $achievement['slug'],
                ],
                [
                    'name' => $achievement['name'],
                    'icon' => $achievement['icon'],
                    'description' => $achievement['description'],
                    'awarded_at' => now(),
                ]
            );
        }
    }
}
