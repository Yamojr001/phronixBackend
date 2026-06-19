<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\Course;
use App\Services\AiService;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use App\Support\TestType;

class TestController extends Controller
{
    protected $aiService;
    protected $gamificationService;

    public function __construct(AiService $aiService, GamificationService $gamificationService)
    {
        $this->aiService = $aiService;
        $this->gamificationService = $gamificationService;
    }

    /**
     * Dashboard with all tests types
     */
    public function index()
    {
        $courses = Auth::user()->courses()->where('semester_id', Auth::user()->current_semester_id)->get();
        return response()->json([
            'courses' => $courses
        ]);
    }

    /**
     * Generate a dynamic test using AI
     */
    public function generate(\App\Http\Requests\Api\GenerateTestRequest $request)
    {
        $validated = $request->validated();

        $course = Auth::user()->courses()->findOrFail($validated['course_id']);
        $testType = TestType::normalize($validated['test_type']);
        $questionCount = $validated['question_count'];
        $isEssay = ($testType === TestType::MOCK_EXAM);

        $lockKey = 'idempotency:test-generate:' . Auth::id() . ':' . $course->id . ':' . $testType;
        $lock = Cache::lock($lockKey, 15);
        if (!$lock->get()) {
            return response()->json(['error' => 'A test generation is already in progress. Please wait a few seconds and retry.'], 429);
        }

        $content = $course->full_content;
        if (!$content || empty($content)) {
            optional($lock)->release();
            return response()->json(['error' => 'Course has no extracted content to test on.'], 422);
        }

        try {
            $testData = $this->aiService->generateTestFromContent($content, $questionCount, $isEssay);
        } finally {
            optional($lock)->release();
        }
        
        if (!$testData) {
            return response()->json(['error' => 'AI failed to generate a test. Please try again.'], 500);
        }

        if (!$isEssay) {
            foreach ($testData['questions'] as &$question) {
                if (isset($question['options']) && isset($question['correct_answer_index'])) {
                    $correctAnswer = $question['options'][$question['correct_answer_index']];
                    shuffle($question['options']);
                    $question['correct_answer_index'] = array_search($correctAnswer, $question['options']);
                }
            }
        }

        $testId = \Illuminate\Support\Str::uuid()->toString();
        Cache::put('test_'.$testId, [
            'questions' => $testData['questions'],
            'course_id' => $course->id,
            'test_type' => $testType,
            'test_name' => TestType::label($testType) . ' Test',
        ], now()->addHour());

        return response()->json([
            'test_id' => $testId,
            'questions' => $isEssay ? $testData['questions'] : array_map(fn($q) => ['question' => $q['question'], 'options' => $q['options']], $testData['questions']),
            'course' => $course,
            'test_type' => $testType,
        ]);
    }

    /**
     * View the actively generated test (Not strictly needed for API if handled by generate, but kept for compatibility)
     */
    public function take(Request $request)
    {
        $testId = $request->query('test_id');
        $data = Cache::get('test_'.$testId);

        if (!$data) {
            return response()->json(['error' => 'Test session expired or invalid.'], 422);
        }

        $course = Course::find($data['course_id']);
        
        return response()->json([
            'course' => $course,
            'questions' => array_map(fn($q) => ['question' => $q['question'], 'options' => $q['options']], $data['questions']),
            'testType' => $data['test_type'],
        ]);
    }

    /**
     * Display the results of a specific test.
     */
    public function showResult(Test $test)
    {
        Gate::authorize('view', $test);
        return response()->json([
            'testResult' => $test->load('course'),
        ]);
    }

    /**
     * Display test creation page for a specific course
     */
    public function create(Request $request, Course $course)
    {
        $user = Auth::user();
        if ($course->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $testType = TestType::normalize($request->input('test_type', TestType::PRE_TEST));
        $testName = $request->input('test_name', TestType::label($testType) . ' Test');
        
        $existingTest = Test::where('course_id', $course->id)
            ->where('type', $testType)
            ->first();

        if ($existingTest) {
            return response()->json([
                'error' => 'You have already taken this test.',
                'test' => $existingTest
            ], 422);
        }

        $content = $course->full_content;
        if (!$content || empty($content)) {
            return response()->json(['error' => 'Course content is missing. Please try re-uploading the course.'], 422);
        }

        $isEssay = ($testType === TestType::MOCK_EXAM);
        $questionCount = $isEssay ? 5 : 50;
        $testData = $this->aiService->generateTestFromContent($content, $questionCount, $isEssay);
        
        if (!$testData) {
            return response()->json(['error' => 'The AI failed to generate a test. Please try again later.'], 500);
        }

        foreach ($testData['questions'] as &$question) {
            if (!$isEssay) {
                $correctAnswer = $question['options'][$question['correct_answer_index']];
                shuffle($question['options']);
                $question['correct_answer_index'] = array_search($correctAnswer, $question['options']);
            }
        }

        $testId = \Illuminate\Support\Str::uuid()->toString();
        Cache::put('test_'.$testId, [
            'questions' => $testData['questions'],
            'course_id' => $course->id,
            'test_type' => $testType,
            'test_name' => $testName,
        ], now()->addHour());

        return response()->json([
            'test_id' => $testId,
            'course' => $course,
            'questions' => $isEssay ? $testData['questions'] : array_map(fn($q) => ['question' => $q['question'], 'options' => $q['options']], $testData['questions']),
            'totalQuestions' => count($testData['questions']),
            'testType' => $testType,
            'testName' => $testName,
        ]);
    }

    /**
     * Store objective test results
     */
    public function storeObjective(\App\Http\Requests\Api\SubmitTestRequest $request)
    {
        $validated = $request->validated();
        $user = Auth::user();
        
        $testId = $validated['test_id'];
        $data = Cache::get('test_'.$testId);
        
        if (!$data) {
            return response()->json(['error' => 'Test session expired. Please start the test again.'], 422);
        }

        $questions = $data['questions'];
        $courseId = $data['course_id'];
        $testType = TestType::normalize((string) $data['test_type']);
        
        $course = Course::find($courseId);
        if (!$course || $course->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized access to course.'], 403);
        }

        $correctCount = 0;
        $weakTopics = [];
        $review = [];
        
        foreach ($questions as $index => $question) {
            $userAnswer = $validated['answers'][$index] ?? null;
            $correctIndex = $question['correct_answer_index'];
            $correctAnswer = $question['options'][$correctIndex] ?? null;
            $userAnswerText = ($userAnswer !== null && isset($question['options'][$userAnswer])) ? $question['options'][$userAnswer] : null;
            $isCorrect = ((string) $userAnswer === (string) $correctIndex);

            $review[] = [
                'question' => $question['question'],
                'options' => $question['options'],
                'correct_answer_index' => $correctIndex,
                'correct_answer' => $correctAnswer,
                'user_answer_index' => $userAnswer,
                'user_answer' => $userAnswerText,
                'is_correct' => $isCorrect,
            ];

            if ($isCorrect) {
                $correctCount++;
            } else {
                $questionText = $question['question'];
                $words = explode(' ', $questionText);
                $topic = implode(' ', array_slice($words, 0, 5));
                $weakTopics[] = $topic . '...';
            }
        }

        $score = round(($correctCount / count($questions)) * 100, 2);
        $weakTopics = array_slice(array_unique($weakTopics), 0, 10);

        $test = Test::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => $testType,
            'score' => $score,
            'weak_topics' => $weakTopics,
            'taken_date' => now(),
        ]);

        Cache::forget('test_'.$testId);
        $this->gamificationService->recordTestResult($user, $score);

        if ($testType !== TestType::PRE_TEST) {
            $this->updateMasterTimetable($user, $testType);
        }

        return response()->json([
            'success' => true,
            'score' => $score,
            'weak_topics' => $weakTopics,
            'test_id' => $test->id,
            'test_result' => $test->load('course'),
            'review' => $review,
        ]);
    }

    /**
     * Store and AI-grade Mock Exam Essay responses
     */
    public function storeEssay(\App\Http\Requests\Api\SubmitTestRequest $request)
    {
        $validated = $request->validated();
        $user = Auth::user();
        
        $testId = $validated['test_id'];
        $data = Cache::get('test_'.$testId);
        
        if (!$data) {
            return response()->json(['error' => 'Test session expired.'], 422);
        }

        $questions = $data['questions'];
        $courseId = $data['course_id'];
        $testType = TestType::normalize((string) $data['test_type']);

        $course = Course::find($courseId);
        if (!$course || $course->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        $aiResult = $this->aiService->markEssayTest($questions, $validated['answers']);
        
        if (!$aiResult) {
            return response()->json(['error' => 'AI grading failed. Please try again.'], 500);
        }

        $test = Test::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => $testType,
            'score' => $aiResult['score'],
            'weak_topics' => array_slice(array_unique($aiResult['weak_topics']), 0, 10),
            'taken_date' => now(),
        ]);

        Cache::forget('test_'.$testId);
        $this->gamificationService->recordTestResult($user, (float) $aiResult['score']);
        $this->updateMasterTimetable($user, $testType);

        return response()->json([
            'success' => true,
            'score' => $aiResult['score'],
            'weak_topics' => $aiResult['weak_topics'],
            'feedback' => $aiResult['feedback'],
            'test_id' => $test->id,
            'test_result' => $test->load('course')
        ]);
    }

    /**
     * Update master timetable after completing a scheduled test
     */
    private function updateMasterTimetable($user, $testType)
    {
        try {
            $timetable = $user->masterTimetable;
            
            if (!$timetable) {
                return;
            }

            // Check if all courses have taken this test type
            $courses = $user->courses()->get();
            $allTestsTaken = true;
            
            foreach ($courses as $course) {
                if (!$course->tests()->where('type', $testType)->exists()) {
                    $allTestsTaken = false;
                    break;
                }
            }
            
            // If all tests taken, update to next test
            if ($allTestsTaken && $timetable->test_schedule) {
                $testSchedule = $timetable->test_schedule;
                $nextTest = null;
                
                foreach ($testSchedule as $test) {
                    if ($test['type'] === $testType) {
                        $currentIndex = array_search($test, $testSchedule);
                        if (isset($testSchedule[$currentIndex + 1])) {
                            $nextTest = $testSchedule[$currentIndex + 1];
                        }
                        break;
                    }
                }
                
                if ($nextTest) {
                    $timetable->update([
                        'next_test_week' => $nextTest['week']
                    ]);
                    
                    Log::info('Updated master timetable next test week', [
                        'user_id' => $user->id,
                        'test_type' => $testType,
                        'next_test_week' => $nextTest['week']
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to update master timetable after test', [
                'user_id' => $user->id,
                'test_type' => $testType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display test history for a course
     */
    public function getCourseTests(Course $course)
    {
        $user = Auth::user();
        
        // Verify the course belongs to the user
        if ($course->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tests = Test::where('course_id', $course->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($test) {
                return [
                    'id' => $test->id,
                    'type' => $test->type,
                    'score' => $test->score,
                    'weak_topics' => $test->weak_topics,
                    'taken_date' => $test->taken_date->format('Y-m-d H:i'),
                    'created_at' => $test->created_at->format('Y-m-d H:i'),
                ];
            });

        return response()->json(['tests' => $tests]);
    }

    /**
     * Get test statistics for dashboard
     */
    public function getTestStatistics()
    {
        $user = Auth::user();
        
        $tests = Test::where('user_id', $user->id)
            ->with('course')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($test) {
                return [
                    'id' => $test->id,
                    'course_name' => $test->course->title,
                    'type' => $this->formatTestType($test->type),
                    'score' => $test->score,
                    'taken_date' => $test->taken_date->format('M d, Y'),
                ];
            });

        $averageScore = Test::where('user_id', $user->id)
            ->avg('score');

        $testCount = Test::where('user_id', $user->id)->count();

        return response()->json([
            'recent_tests' => $tests,
            'average_score' => round($averageScore, 2),
            'test_count' => $testCount,
        ]);
    }

    /**
     * Format test type for display
     */
    private function formatTestType($type)
    {
   
        $types = [
            TestType::PRE_TEST => 'Pre-Test',
            TestType::MID_SEMESTER => 'Mid-Semester',
            TestType::POST_TEST => 'Post-Test',
            TestType::MOCK_EXAM => 'Mock Exam',
            TestType::RANDOM_TEST => 'Random Test',
        ];

        $normalized = TestType::normalize((string) $type);
        return $types[$normalized] ?? ucfirst(str_replace('_', ' ', $normalized));
    }

    /**
     * Delete a test
     */
    public function destroy(Test $test)
    {
        Gate::authorize('delete', $test);
        
        $test->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Test deleted successfully.'
        ]);
    }
}