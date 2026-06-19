<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\MasterTimetableController;
use App\Http\Controllers\ReadingPlanController;
use Illuminate\Http\Request;

use App\Http\Controllers\HistoryController;
use App\Http\Controllers\PastQuestionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StudyRoomController;
use App\Http\Controllers\TutorController;
use App\Http\Controllers\GamificationController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StudyAideController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\SemesterOptionController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
    
    // Semester Management
    Route::post('/semesters', [SemesterController::class, 'store']);
    Route::post('/semesters/switch', [SemesterController::class, 'switch']);
    
    // Course Management & Testing
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses/extract-text', [CourseController::class, 'extractText'])->middleware('throttle:ai-heavy');
    Route::post('/courses', [CourseController::class, 'store']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::get('/courses/{course}/pre-test', [CourseController::class, 'showTest']);
    Route::post('/courses/{course}/pre-test', [CourseController::class, 'storeTest']);
    Route::post('/courses/{course}/retry-analysis', [CourseController::class, 'retryAnalysis']);

    // Advanced Testing Dashboard
    Route::get('/assessment', [TestController::class, 'index']);
    Route::post('/assessment/generate', [TestController::class, 'generate'])->middleware('throttle:ai-heavy');
    Route::get('/assessment/{course}/create', [TestController::class, 'create']);
    Route::get('/assessment/take', [TestController::class, 'take']);
    Route::post('/assessment/store-objective', [TestController::class, 'storeObjective'])->middleware('throttle:ai-heavy');
    Route::post('/assessment/store-essay', [TestController::class, 'storeEssay'])->middleware('throttle:ai-heavy');
    Route::get('/assessment/{test}/results', [TestController::class, 'showResult']);

    // AI Suggestion Routes
    Route::get('/courses/{course}/suggestion', [SuggestionController::class, 'show']);
    Route::post('/courses/{course}/suggestion', [SuggestionController::class, 'generate'])->middleware('throttle:ai-heavy');
    Route::get('/suggestion/{suggestion}/download', [SuggestionController::class, 'download']);

    // Master Timetable Routes
    Route::get('/master-timetable', [MasterTimetableController::class, 'show']);
    Route::post('/master-timetable', [MasterTimetableController::class, 'generate'])->middleware('throttle:ai-heavy');
    Route::get('/master-timetable/download', [MasterTimetableController::class, 'download']);
    Route::get('/master-timetable/week/{week}', [MasterTimetableController::class, 'getWeek']);
    Route::get('/master-timetable/start-test', [MasterTimetableController::class, 'startTest']);

    // Reading Plan Route
    Route::get('/reading-plan', [ReadingPlanController::class, 'index']);
    Route::get('/reading-handouts', [ReadingPlanController::class, 'handouts']);
    Route::post('/reading-plan/{course}/generate', [ReadingPlanController::class, 'generate'])->middleware('throttle:ai-heavy');
    Route::post('/reading-handouts/{course}/generate', [ReadingPlanController::class, 'generateHandout'])->middleware('throttle:ai-heavy');
    Route::get('/reading-plan/{course}/view', [ReadingPlanController::class, 'showDetailed']);
    Route::get('/reading-plan/{course}/download-handout', [ReadingPlanController::class, 'downloadHandout']);

    // History Route
    Route::get('/history', [HistoryController::class, 'index']);

    // Tutor and Read Aloud
    Route::post('/tutor/explain', [TutorController::class, 'explain'])->middleware('throttle:ai-heavy');

    // Past Questions Routes
    Route::get('/past-questions', [PastQuestionController::class, 'index']);
    Route::get('/past-questions/upload', [PastQuestionController::class, 'create']);
    Route::post('/past-questions/upload', [PastQuestionController::class, 'store']);
    Route::get('/past-questions/{pastQuestion}/solve', [PastQuestionController::class, 'solve']);
    Route::post('/past-questions/{pastQuestion}/ai-solve', [PastQuestionController::class, 'aiSolve'])->middleware('throttle:ai-heavy');
    Route::post('/past-questions/{pastQuestion}/grade', [PastQuestionController::class, 'grade'])->middleware('throttle:ai-heavy');
    Route::get('/past-questions/{pastQuestion}/download', [PastQuestionController::class, 'download']);

    // Review & Suggestion Routes
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Study Room Routes
    Route::get('/study-room', [StudyRoomController::class, 'index']);
    Route::get('/study-room/{course}', [StudyRoomController::class, 'show']);
    Route::post('/study-room/explain', [StudyRoomController::class, 'explain'])->middleware('throttle:ai-heavy');
    Route::post('/study-room/{course}/generate-test', [StudyRoomController::class, 'generateTest'])->middleware('throttle:ai-heavy');
    Route::post('/study-room/mark-reading', [StudyRoomController::class, 'markReading']);
    Route::post('/study-room/submit-test', [StudyRoomController::class, 'submitTest']);
    Route::post('/study-room/toggle-task', [StudyRoomController::class, 'toggleTask']);

    // Gamification Routes
    Route::get('/gamification/overview', [GamificationController::class, 'overview']);
    Route::get('/gamification/leaderboard', [GamificationController::class, 'leaderboard']);
    Route::post('/gamification/record-time', [GamificationController::class, 'recordTimeSpent']);

    // Quiz & Challenge Routes
    Route::get('/quizzes', [QuizController::class, 'index']);
    Route::post('/quizzes', [QuizController::class, 'store']);
    Route::get('/quizzes/create', [QuizController::class, 'create']);
    Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
    Route::post('/quizzes/{quiz}/submit-for-review', [QuizController::class, 'submitForReview']);
    Route::post('/quizzes/{quiz}/start', [QuizController::class, 'startAttempt']);
    Route::post('/quiz-attempts/{attempt}/submit', [QuizController::class, 'submitAnswers']);
    
    // User Quiz Routes
    Route::get('/my-quiz-attempts', [QuizController::class, 'getUserAttempts']);
    Route::get('/my-quizzes', [QuizController::class, 'getUserCreated']);
    Route::get('/recommended-quizzes', [QuizController::class, 'getRecommended']);
    Route::get('/quiz-progress', [QuizController::class, 'getProgress']);
    Route::get('/quiz-categories', [QuizController::class, 'getCategories']);

    // Study Aide / Summary Routes
    Route::prefix('study-aide')->group(function () {
        Route::match(['get', 'post'], '/summarize', function (Request $request) {
            if ($request->isMethod('post')) {
                return app(StudyAideController::class)->summarize($request);
            }
            return app(StudyAideController::class)->info();
        })->middleware(['throttle:ai-heavy']);
        Route::get('/summaries', [StudyAideController::class, 'history']);
        Route::get('/summaries/{studySummary}', [StudyAideController::class, 'show']);
        Route::delete('/summaries/{studySummary}', [StudyAideController::class, 'destroy']);
    });
});

Route::get('/universities', [App\Http\Controllers\UniversityController::class, 'index']);
Route::post('/universities', [App\Http\Controllers\UniversityController::class, 'store']);
Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/departments', [DepartmentController::class, 'store']);
Route::get('/semester-options', [SemesterOptionController::class, 'index']);

// Auth Routes (Login/Register already refactored in Controllers, but we need routes for them)
Route::post('/forgot-password', [App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store']);
Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);
Route::post('/register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store']);
Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])->middleware('auth:sanctum');

// Admin Routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard']);
    Route::get('/users', [\App\Http\Controllers\Admin\AdminController::class, 'users']);
    Route::post('/users/{user}/toggle-admin', [\App\Http\Controllers\Admin\AdminController::class, 'toggleAdmin']);
    Route::post('/users/{user}/toggle-status', [\App\Http\Controllers\Admin\AdminController::class, 'toggleStatus']);
    Route::get('/courses', [\App\Http\Controllers\Admin\AdminController::class, 'courses']);
    Route::get('/past-questions', [\App\Http\Controllers\Admin\AdminController::class, 'pastQuestions']);
    Route::get('/logs', [\App\Http\Controllers\Admin\AdminController::class, 'logs']);
    Route::get('/settings', [\App\Http\Controllers\Admin\AdminController::class, 'settings']);
    Route::get('/newsletter', [\App\Http\Controllers\Admin\AdminController::class, 'newsletter']);
    Route::post('/newsletter/send', [\App\Http\Controllers\Admin\AdminController::class, 'sendNewsletter']);

    Route::get('/reviews', [\App\Http\Controllers\Admin\ReviewController::class, 'index']);
    Route::post('/reviews/{review}/read', [\App\Http\Controllers\Admin\ReviewController::class, 'markAsRead']);
    Route::delete('/reviews/{review}', [\App\Http\Controllers\Admin\ReviewController::class, 'destroy']);

    Route::get('/notifications', [\App\Http\Controllers\Admin\AdminController::class, 'notifications']);
    Route::post('/notifications', [\App\Http\Controllers\Admin\AdminController::class, 'sendNotification']);
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\Admin\AdminController::class, 'deleteNotification']);

    Route::get('/badge-progression', [\App\Http\Controllers\Admin\AdminController::class, 'badgeProgression']);
    Route::get('/badge-analytics', [\App\Http\Controllers\Admin\AdminController::class, 'badgeAnalytics']);

    // Quiz Management Routes
    Route::get('/quizzes-pending', [QuizController::class, 'getPendingQuizzes']);
    Route::post('/quizzes/{quiz}/approve', [QuizController::class, 'approveQuiz']);
    Route::post('/quizzes/{quiz}/reject', [QuizController::class, 'rejectQuiz']);
});
