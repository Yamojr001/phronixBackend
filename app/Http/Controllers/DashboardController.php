<?php

namespace App\Http\Controllers;

use App\Models\GamificationProfile;
use App\Models\StudyProgress;
use App\Models\Test;
use App\Models\UserBadge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $semesterId = $user->current_semester_id;

        // 1. Get the user's most recent courses constrained to the active semester
        $recentCourses = $user->courses()
                              ->where('semester_id', $semesterId)
                              ->orderBy('created_at', 'desc')
                              ->take(5)
                              ->get();

        // 2. Get the user's latest test result for the active semester
        $latestTest = $user->tests()
                           ->whereHas('course', function($query) use ($semesterId) {
                               $query->where('semester_id', $semesterId);
                           })
                           ->with('course')
                           ->latest()
                           ->first();

        // 3. Calculate overall statistics constraint to active semester
        $totalCourses = $user->courses()->where('semester_id', $semesterId)->count();
        $averageScore = $user->tests()
                                   ->whereHas('course', function($query) use ($semesterId) {
                                       $query->where('semester_id', $semesterId);
                                   })
                                   ->avg('score');

        $studyProgress = StudyProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('course', function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId);
            })
            ->get();

        $testsQuery = Test::query()
            ->where('user_id', $user->id)
            ->whereHas('course', function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId);
            });

        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();

        $todayEntries = $studyProgress->filter(fn ($progress) => $progress->updated_at?->isSameDay($today));
        $todayReadingMinutes = (int) $studyProgress
            ->filter(fn ($progress) => $progress->updated_at?->isSameDay($today) && (int) ($progress->reading_minutes ?? 0) > 0)
            ->sum('reading_minutes');
        $todayReadingCompleted = $studyProgress
            ->contains(fn ($progress) => $progress->updated_at?->isSameDay($today) && ((bool) $progress->reading_completed || (int) ($progress->reading_minutes ?? 0) >= 15));
        $todayTasksCompleted = (int) $todayEntries->sum(fn ($progress) => count($progress->completed_tasks ?? []));

        $todayTests = (clone $testsQuery)
            ->where(function ($query) use ($today) {
                $query->whereDate('taken_date', $today)
                    ->orWhereDate('created_at', $today);
            })
            ->get();

        $todayTestsCompleted = (int) $todayTests->count();
        $todayLatestTest = $todayTests->sortByDesc(fn ($test) => $test->taken_date ?? $test->created_at)->first();

        $todayTasksTotal = (int) $todayEntries->sum(function ($progress) {
            $course = $progress->course;
            if (!$course || empty($course->generated_handout)) {
                return 0;
            }

            $handout = json_decode($course->generated_handout, true);
            if (!is_array($handout) || empty($handout['weeks']) || !is_array($handout['weeks'])) {
                return 0;
            }

            foreach ($handout['weeks'] as $week) {
                if ((int) ($week['week_number'] ?? 0) === (int) $progress->week_number) {
                    $tasks = $week['days'][strtolower((string) $progress->day_name)]['tasks'] ?? [];
                    return is_array($tasks) ? count($tasks) : 0;
                }
            }

            return 0;
        });

        if ($todayTasksTotal === 0 && $todayTasksCompleted > 0) {
            // Fallback avoids misleading 6/0 display when task metadata is unavailable.
            $todayTasksTotal = $todayTasksCompleted;
        }

        $testsCompletedThisWeek = (clone $testsQuery)
            ->where(function ($query) use ($weekStart) {
                $query->whereDate('taken_date', '>=', $weekStart->toDateString())
                    ->orWhereDate('created_at', '>=', $weekStart->toDateString());
            })
            ->count();

        $weeklyReadingTrend = collect(range(0, 6))->map(function ($offset) use ($weekStart, $user, $semesterId) {
            $date = $weekStart->copy()->addDays($offset);

            $minutes = StudyProgress::query()
                ->where('user_id', $user->id)
                ->whereHas('course', function ($query) use ($semesterId) {
                    $query->where('semester_id', $semesterId);
                })
                ->whereDate('updated_at', $date)
                ->where('reading_minutes', '>', 0)
                ->sum('reading_minutes');

            return [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('D'),
                'minutes' => (int) $minutes,
                'completed' => $minutes > 0,
            ];
        });

        $gamificationService = app(\App\Services\GamificationService::class);
        $gamificationService->syncBadges($user); // This will ensure profile exists and award badges
        $gamificationService->touchActivity($user); // Daily check-in

        $currentProfile = $gamificationService->ensureProfile($user);
        $achievementsUnlocked = UserBadge::where('user_id', $user->id)->count();

        // 4. Get semester information from master timetable
        $timetable = $user->masterTimetable;
        $semesterInfo = null;
        if ($timetable) {
            $semesterInfo = [
                'current_week' => $timetable->current_week,
                'total_weeks' => $timetable->semester_duration_weeks,
                'start_date' => $timetable->semester_start_date?->format('Y-m-d'),
                'has_timetable' => true,
            ];
        }

        // 5. Fetch active system notifications
        $systemNotifications = \App\Models\SystemNotification::active()->latest()->get();

        // Pass all this data as JSON
        return response()->json([
            'recentCourses' => $recentCourses,
            'latestTest' => $latestTest,
            'stats' => [
                'totalCourses' => $totalCourses,
                'averageScore' => round($averageScore), // Round to a whole number
                'readingMinutesThisWeek' => (int) $studyProgress
                    ->filter(fn ($progress) => $progress->updated_at?->greaterThanOrEqualTo($weekStart) && (int) ($progress->reading_minutes ?? 0) > 0)
                    ->sum('reading_minutes'),
                'readingDaysThisWeek' => (int) $studyProgress
                    ->filter(fn ($progress) => $progress->updated_at?->greaterThanOrEqualTo($weekStart) && (int) ($progress->reading_minutes ?? 0) > 0)
                    ->count(),
                'testsCompletedThisWeek' => (int) $testsCompletedThisWeek,
                'achievementsUnlocked' => $achievementsUnlocked,
            ],
            'analytics' => [
                'dailyProgress' => [
                    'score' => (int) round(((($todayReadingCompleted ? 100 : min(100, (int) round(($todayReadingMinutes / 15) * 100))) * 0.45) + (($todayTasksTotal > 0 ? min(100, (int) round(($todayTasksCompleted / max(1, $todayTasksTotal)) * 100)) : 0) * 0.35) + ($todayTestsCompleted > 0 ? 20 : 0))),
                    'reading' => $todayReadingCompleted ? 100 : min(100, (int) round(($todayReadingMinutes / 15) * 100)),
                    'tasks' => $todayTasksTotal > 0 ? min(100, (int) round(($todayTasksCompleted / max(1, $todayTasksTotal)) * 100)) : 0,
                    'test' => $todayTestsCompleted > 0 ? 100 : 0,
                    'reading_minutes' => $todayReadingMinutes,
                    'reading_completed' => $todayReadingCompleted,
                    'tasks_completed' => $todayTasksCompleted,
                    'tasks_total' => $todayTasksTotal,
                    'tests_completed' => $todayTestsCompleted,
                    'test_score' => $todayLatestTest?->score,
                ],
                'weeklyReadingTrend' => $weeklyReadingTrend,
                'todaySnapshot' => [
                    'readingMinutes' => $todayReadingMinutes,
                    'completedItems' => $todayEntries->count(),
                    'currentLevel' => $currentProfile->level,
                    'currentXp' => $currentProfile->xp,
                ],
            ],
            'semesterInfo' => $semesterInfo,
            'systemNotifications' => $systemNotifications,
        ]);
    }
}