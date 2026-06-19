<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard()
    {
        // Fetch System Statistics
        $stats = [
            'total_users' => \App\Models\User::count(),
            'total_courses' => \App\Models\Course::count(),
            'total_tests' => \App\Models\Test::count(),
            'total_past_questions' => \App\Models\PastQuestion::count(),
        ];

        // Fetch Recent Activity
        $recent_users = \App\Models\User::latest()->take(5)->get(['id', 'name', 'email', 'created_at']);
        
        $recent_uploads = \App\Models\PastQuestion::with('user:id,name')
            ->latest()
            ->take(5)
            ->get(['id', 'user_id', 'school', 'course_code', 'created_at']);

        if (request()->expectsJson()) {
            return response()->json([
                'stats' => $stats,
                'recentUsers' => $recent_users,
                'recentUploads' => $recent_uploads,
            ]);
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentUsers' => $recent_users,
            'recentUploads' => $recent_uploads,
        ]);
    }

    public function users()
    {
        $users = \App\Models\User::latest()->paginate(10);

        if (request()->expectsJson()) {
            return response()->json([
                'users' => $users,
            ]);
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function toggleAdmin(\App\Models\User $user)
    {
        // Prevent self-demotion
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot demote yourself.');
        }

        $user->update(['is_admin' => !$user->is_admin]);

        return back()->with('success', 'User role updated successfully.');
    }

    public function toggleStatus(\App\Models\User $user)
    {
        // Prevent self-deactivation
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'User status updated successfully.');
    }

    public function courses()
    {
        $courses = \App\Models\Course::with('user:id,name,school')->latest()->paginate(10);

        if (request()->expectsJson()) {
            return response()->json([
                'courses' => $courses,
            ]);
        }

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
        ]);
    }

    public function pastQuestions()
    {
        $pastQuestions = \App\Models\PastQuestion::with('user:id,name')->latest()->paginate(10);

        if (request()->expectsJson()) {
            return response()->json([
                'pastQuestions' => $pastQuestions,
            ]);
        }

        return Inertia::render('Admin/PastQuestions/Index', [
            'pastQuestions' => $pastQuestions,
        ]);
    }

    public function logs(Request $request)
    {
        $tab = $request->get('tab', 'all');
        
        $query = \App\Models\ActivityLog::with('user:id,name')->latest();

        if ($tab === 'signups') {
            $query->where('type', 'Signup');
        } elseif ($tab === 'academic') {
            $query->where('type', 'Academic Content');
        } elseif ($tab === 'ai') {
            $query->where('type', 'AI Query');
        }

        $logs = $query->paginate(20)->withQueryString();

        $sessions = [];
        if ($tab === 'sessions') {
            $sessions = \App\Models\LoginActivity::with('user:id,name')
                ->latest()
                ->paginate(20)
                ->withQueryString();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'logs' => $logs,
                'sessions' => $sessions,
                'currentTab' => $tab,
            ]);
        }

        return Inertia::render('Admin/Logs/Index', [
            'logs' => $logs,
            'sessions' => $sessions,
            'currentTab' => $tab,
        ]);
    }

    public function settings()
    {
        $settings = [
            'app_name' => config('app.name'),
            'ai_model' => 'Gemini 2.5 Flash',
            'ai_daily_request_limit' => config('services.gemini.daily_request_limit'),
            'environment' => config('app.env'),
        ];

        if (request()->expectsJson()) {
            return response()->json([
                'settings' => $settings,
            ]);
        }

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function newsletter()
    {
        $stats = [
            'total_subscribers' => \App\Models\User::where('subscribed_to_newsletter', true)->count(),
        ];

        if (request()->expectsJson()) {
            return response()->json([
                'stats' => $stats,
            ]);
        }

        return Inertia::render('Admin/Newsletter/Index', [
            'stats' => $stats,
        ]);
    }

    public function sendNewsletter(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $subscribers = \App\Models\User::query()
            ->where('subscribed_to_newsletter', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        if ($subscribers->isEmpty()) {
            return back()->with('error', 'No subscribed users with valid email addresses were found.');
        }

        $totalSubscribers = $subscribers->count();
        $sentCount = 0;
        $failedCount = 0;

        foreach ($subscribers as $subscriber) {
            try {
                \Illuminate\Support\Facades\Mail::to($subscriber->email)
                    ->send(new \App\Mail\NewsletterMail($request->subject, $request->content, $subscriber));
                $sentCount++;

                \Log::info('Newsletter sent attempt accepted by SMTP', [
                    'user_id' => $subscriber->id,
                    'email' => $subscriber->email,
                ]);
            } catch (\Throwable $e) {
                $failedCount++;
                \Log::error('Newsletter send failed for subscriber', [
                    'user_id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "Newsletter broadcast complete. Sent to {$sentCount} of {$totalSubscribers} subscribed user(s).";
        if ($failedCount > 0) {
            $message .= " Failed: {$failedCount}.";
        }

        \Log::info('Newsletter broadcast summary', [
            'total_subscribers' => $totalSubscribers,
            'sent' => $sentCount,
            'failed' => $failedCount,
        ]);

        return back()->with('success', $message);
    }

    public function notifications()
    {
        $notifications = \App\Models\SystemNotification::with('user:id,name')->latest()->paginate(10);

        if (request()->expectsJson()) {
            return response()->json([
                'notifications' => $notifications,
            ]);
        }

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function sendNotification(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:info,success,warning,danger',
        ]);

        \App\Models\SystemNotification::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'type' => $validated['type'],
            // Auto-delete after 10 days
            'expires_at' => now()->addDays(10),
        ]);

        return back()->with('success', 'System notification broadcasted successfully and will expire in 10 days.');
    }

    public function deleteNotification(\App\Models\SystemNotification $notification)
    {
        $notification->delete();
        return back()->with('success', 'Notification deleted.');
    }

    /**
     * Get badge progression system details
     */
    public function badgeProgression()
    {
        $badges = \App\Models\BadgeDefinition::where('is_active', true)
            ->orderBy('xp_required')
            ->get()
            ->groupBy('tier');

        // Get stats on badge distribution
        $userBadgeStats = [];
        foreach ($badges as $tier => $tierBadges) {
            foreach ($tierBadges as $badge) {
                $earnedCount = \App\Models\UserBadge::where('slug', $badge->slug)->count();
                $badge->earned_count = $earnedCount;
                $badge->earned_percentage = round(($earnedCount / max(1, \App\Models\User::count())) * 100, 2);
            }
        }

        if (request()->expectsJson()) {
            return response()->json([
                'badges' => $badges,
                'total_badge_definitions' => \App\Models\BadgeDefinition::count(),
                'users_with_badges' => \App\Models\UserBadge::distinct('user_id')->count(),
            ]);
        }

        return Inertia::render('Admin/BadgeProgression', [
            'badges' => $badges,
            'total_badge_definitions' => \App\Models\BadgeDefinition::count(),
            'users_with_badges' => \App\Models\UserBadge::distinct('user_id')->count(),
        ]);
    }

    /**
     * Get analytics for badge achievements
     */
    public function badgeAnalytics()
    {
        // Most earned badges
        $mostEarned = \App\Models\UserBadge::selectRaw('slug, name, COUNT(*) as earned_count')
            ->groupBy('slug', 'name')
            ->orderByDesc('earned_count')
            ->take(10)
            ->get();

        // Users closest to next badge
        $nextBadgeCandidates = \App\Models\GamificationProfile::with('user:id,name')
            ->orderByDesc('xp')
            ->take(20)
            ->get()
            ->map(function ($profile) {
                $nextBadge = \App\Models\BadgeDefinition::where('xp_required', '>', $profile->xp)
                    ->orderBy('xp_required')
                    ->first();

                return [
                    'user_id' => $profile->user_id,
                    'user_name' => $profile->user?->name,
                    'current_xp' => $profile->xp,
                    'next_badge' => $nextBadge?->name,
                    'xp_to_next' => $nextBadge ? $nextBadge->xp_required - $profile->xp : 0,
                    'progress_percent' => $nextBadge ? round((($profile->xp / $nextBadge->xp_required) * 100), 2) : 100,
                ];
            });

        // Badge tier distribution
        $tierDistribution = \App\Models\BadgeDefinition::select('tier')
            ->whereHas('userBadges')
            ->selectRaw('tier, COUNT(DISTINCT user_id) as user_count')
            ->groupBy('tier')
            ->get();

        if (request()->expectsJson()) {
            return response()->json([
                'most_earned' => $mostEarned,
                'next_badge_candidates' => $nextBadgeCandidates,
                'tier_distribution' => $tierDistribution,
            ]);
        }

        return Inertia::render('Admin/BadgeAnalytics', [
            'most_earned' => $mostEarned,
            'next_badge_candidates' => $nextBadgeCandidates,
            'tier_distribution' => $tierDistribution,
        ]);
    }
}