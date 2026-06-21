<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class QuizController extends Controller
{
    private QuizService $quizService;

    public function __construct(QuizService $quizService)
    {
        $this->quizService = $quizService;
    }

    /**
     * Get all published quizzes with filters
     */
    public function index(Request $request)
    {
        $query = Quiz::published()
            ->with('creator:id,name', 'questions')
            ->withCount('attempts');

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by difficulty
        if ($request->filled('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        // Search by title/description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $quizzes = $query->paginate(12);

        if ($request->expectsJson()) {
            return response()->json($quizzes);
        }

        return Inertia::render('Quiz/Index', ['quizzes' => $quizzes]);
    }

    /**
     * Create a new quiz
     */
    public function create()
    {
        return Inertia::render('Quiz/Create');
    }

    /**
     * Store a new quiz
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|string',
                'difficulty_level' => 'required|in:easy,medium,hard,expert',
                'questions' => 'required|array|min:1',
                'questions.*.question_text' => 'required|string',
                'questions.*.question_type' => 'required|in:short_answer,essay,select_best,multiple_choice',
                'questions.*.options' => 'nullable|array',
                'questions.*.options.*' => 'nullable|string',
                'questions.*.correct_answer' => 'required|string',
                'questions.*.answer_explanation' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Quiz Validation Failed: ' . json_encode($e->errors()));
            throw $e;
        }

        $quiz = $this->quizService->createQuiz(Auth::user(), $validated);

        return response()->json([
            'success' => true,
            'quiz_id' => $quiz->id,
            'message' => 'Quiz created successfully. It\'s now in draft mode.',
        ]);
    }

    /**
     * Get quiz details
     */
    public function show(Quiz $quiz)
    {
        $this->authorize('view', $quiz);

        $quiz->load('creator:id,name', 'questions');

        if (request()->expectsJson()) {
            return response()->json($quiz);
        }

        return Inertia::render('Quiz/Show', ['quiz' => $quiz]);
    }

    /**
     * Submit quiz for AI review
     */
    public function submitForReview(Quiz $quiz)
    {
        $this->authorize('update', $quiz);

        $result = $this->quizService->submitQuizForReview($quiz);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 400);
    }

    /**
     * Start a quiz attempt
     */
    public function startAttempt(Quiz $quiz)
    {
        $this->authorize('view', $quiz);

        if (!$quiz->is_published) {
            return response()->json(['error' => 'Quiz is not published'], 403);
        }

        $attempt = $this->quizService->startQuizAttempt(Auth::user(), $quiz);

        return response()->json([
            'attempt_id' => $attempt->id,
            'quiz' => $quiz->load('questions'),
        ]);
    }

    /**
     * Submit quiz answers and get results
     */
    public function submitAnswers(Request $request, QuizAttempt $attempt)
    {
        $this->authorize('view', $attempt);

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'required|string',
        ]);

        $result = $this->quizService->submitQuizAnswers($attempt, $validated['answers']);

        return response()->json($result);
    }

    /**
     * Get user's quiz attempts
     */
    public function getUserAttempts()
    {
        $attempts = QuizAttempt::where('user_id', Auth::id())
            ->with('quiz:id,title,creator_id', 'submissions')
            ->orderBy('completed_at', 'desc')
            ->paginate(10);

        if (request()->expectsJson()) {
            return response()->json($attempts);
        }

        return Inertia::render('Quiz/MyAttempts', ['attempts' => $attempts]);
    }

    /**
     * Get user's created quizzes
     */
    public function getUserCreated()
    {
        $quizzes = Quiz::where('creator_id', Auth::id())
            ->withCount('attempts')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        if (request()->expectsJson()) {
            return response()->json($quizzes);
        }

        return Inertia::render('Quiz/MyQuizzes', ['quizzes' => $quizzes]);
    }

    /**
     * Get recommended quizzes
     */
    public function getRecommended()
    {
        $recommended = $this->quizService->getRecommendedQuizzes(Auth::user(), 6);

        if (request()->expectsJson()) {
            return response()->json($recommended);
        }

        return Inertia::render('Quiz/Recommended', ['quizzes' => $recommended]);
    }

    /**
     * Get user's quiz progress
     */
    public function getProgress()
    {
        $progress = $this->quizService->getUserQuizProgress(Auth::user());

        return response()->json($progress);
    }

    /**
     * Get quiz categories available
     */
    public function getCategories()
    {
        $categories = Quiz::published()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values();

        return response()->json($categories);
    }

    /**
     * Get pending quizzes for admin approval
     */
    public function getPendingQuizzes()
    {
        $quizzes = Quiz::where('status', 'pending_review')
            ->with('creator:id,name', 'questions')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        if (request()->expectsJson()) {
            return response()->json($quizzes);
        }

        return Inertia::render('Admin/PendingQuizzes', ['quizzes' => $quizzes]);
    }

    /**
     * Approve a quiz
     */
    public function approveQuiz(Quiz $quiz)
    {
        $this->authorize('approve', $quiz);

        $success = $this->quizService->approveQuiz($quiz);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Quiz approved and published successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to approve quiz.',
        ], 400);
    }

    /**
     * Reject a quiz with feedback
     */
    public function rejectQuiz(Request $request, Quiz $quiz)
    {
        $this->authorize('approve', $quiz);

        $validated = $request->validate([
            'feedback' => 'required|string|max:1000',
        ]);

        $success = $this->quizService->rejectQuiz($quiz, $validated['feedback']);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Quiz rejected. Creator has been notified.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to reject quiz.',
        ], 400);
    }
}
