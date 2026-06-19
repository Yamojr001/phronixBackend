<?php

namespace App\Http\Controllers;

use App\Models\GamificationProfile;
use App\Models\Test;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GamificationController extends Controller
{
    public function __construct(private readonly GamificationService $gamificationService)
    {
    }

    public function overview()
    {
        $user = Auth::user();
        $profile = $this->gamificationService->ensureProfile($user);
        $badges = $this->gamificationService->syncBadges($user);
        $badgeDefinitions = $this->gamificationService->getBadgeDefinitions();
        $nextBadge = $this->gamificationService->getNextBadge($user);
        $dailyChallenges = $this->gamificationService->getDailyChallenges($user);

        $xpRank = GamificationProfile::where('xp', '>', $profile->xp)->count() + 1;

        $avgScore = (float) Test::where('user_id', $user->id)->avg('score');
        $testRank = Test::selectRaw('user_id, AVG(score) as avg_score')
            ->groupBy('user_id')
            ->havingRaw('AVG(score) > ?', [$avgScore])
            ->count() + 1;

        return response()->json([
            'profile' => $profile,
            'badges' => $badges,
            'badge_definitions' => $badgeDefinitions,
            'next_badge' => $nextBadge,
            'badge_progress' => [
                'unlocked' => $badges->count(),
                'total' => $badgeDefinitions->count(),
            ],
            'daily_challenges' => $dailyChallenges,
            'ranks' => [
                'xp_rank' => $xpRank,
                'test_score_rank' => $testRank,
            ],
        ]);
    }

    public function leaderboard(Request $request)
    {
        $type = $request->query('type', 'xp');
        $limit = max(5, min((int) $request->query('limit', 20), 50));

        if ($type === 'time_spent') {
            $rows = GamificationProfile::query()
                ->with('user:id,name')
                ->orderByDesc('total_focus_minutes')
                ->take($limit)
                ->get()
                ->values()
                ->map(fn ($row, $index) => [
                    'rank' => $index + 1,
                    'user_id' => $row->user_id,
                    'name' => $row->user?->name,
                    'value' => $row->total_focus_minutes,
                    'label' => $row->total_focus_minutes . ' mins',
                ]);

            return response()->json(['type' => $type, 'entries' => $rows]);
        }

        if ($type === 'test_score') {
            $rows = Test::query()
                ->join('users', 'users.id', '=', 'tests.user_id')
                ->selectRaw('tests.user_id, users.name, AVG(tests.score) as average_score')
                ->groupBy('tests.user_id', 'users.name')
                ->orderByDesc('average_score')
                ->take($limit)
                ->get()
                ->values()
                ->map(fn ($row, $index) => [
                    'rank' => $index + 1,
                    'user_id' => $row->user_id,
                    'name' => $row->name,
                    'value' => round((float) $row->average_score, 2),
                    'label' => round((float) $row->average_score, 2) . '% avg',
                ]);

            return response()->json(['type' => $type, 'entries' => $rows]);
        }

        $rows = GamificationProfile::query()
            ->with('user:id,name')
            ->orderByDesc('xp')
            ->take($limit)
            ->get()
            ->values()
            ->map(fn ($row, $index) => [
                'rank' => $index + 1,
                'user_id' => $row->user_id,
                'name' => $row->user?->name,
                'value' => $row->xp,
                'label' => $row->xp . ' XP',
                'level' => $row->level,
            ]);

        return response()->json(['type' => 'xp', 'entries' => $rows]);
    }

    public function recordTimeSpent(Request $request)
    {
        $data = $request->validate([
            'minutes' => 'required|integer|min:1|max:240',
        ]);

        $profile = $this->gamificationService->recordStudyMinutes(Auth::user(), $data['minutes']);

        return response()->json([
            'message' => 'Time tracked successfully.',
            'profile' => $profile,
        ]);
    }
}
