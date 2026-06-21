<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\QuizSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class QuizService
{
    private AiService $aiService;
    private GamificationService $gamificationService;

    public function __construct(AiService $aiService, GamificationService $gamificationService)
    {
        $this->aiService = $aiService;
        $this->gamificationService = $gamificationService;
    }

    /**
     * Create a new quiz
     */
    public function createQuiz(User $creator, array $data): Quiz
    {
        $quiz = Quiz::create([
            'creator_id' => $creator->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'difficulty_level' => $data['difficulty_level'] ?? 'medium',
            'status' => 'draft',
        ]);

        // Create questions
        foreach ($data['questions'] as $index => $questionData) {
            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question_text' => $questionData['question_text'],
                'question_type' => $questionData['question_type'] ?? 'short_answer',
                'options' => $questionData['options'] ?? null,
                'correct_answer' => $questionData['correct_answer'],
                'answer_explanation' => $questionData['answer_explanation'] ?? null,
                'order' => $index + 1,
            ]);
        }

        return $quiz;
    }

    /**
     * Submit quiz for AI analysis and approval
     */
    public function submitQuizForReview(Quiz $quiz): array
    {
        $questions = $quiz->questions()->get();
        $analysisPrompt = $this->buildQuizAnalysisPrompt($quiz, $questions);

        try {
            $analysis = $this->aiService->callGemini(
                $analysisPrompt,
                [
                    'temperature' => 0.7,
                    'candidateCount' => 1,
                ]
            );

            $parsedAnalysis = $this->parseAiAnalysis($analysis);

            $quiz->update([
                'status' => 'pending_review',
                'ai_analysis' => $parsedAnalysis,
            ]);

            return [
                'success' => true,
                'analysis' => $parsedAnalysis,
                'message' => 'Quiz submitted for review successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Quiz AI analysis failed', [
                'quiz_id' => $quiz->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to analyze quiz. Please try again.',
            ];
        }
    }

    /**
     * Start a quiz attempt
     */
    public function startQuizAttempt(User $user, Quiz $quiz): QuizAttempt
    {
        return QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'started_at' => now(),
        ]);
    }

    /**
     * Submit all answers for a quiz and get AI evaluation
     */
    public function submitQuizAnswers(QuizAttempt $attempt, array $answers): array
    {
        $quiz = $attempt->quiz;
        $totalQuestions = $quiz->questions()->count();
        $correctCount = 0;

        foreach ($answers as $questionId => $userAnswer) {
            $question = QuizQuestion::find($questionId);
            if (!$question) continue;

            // Use AI to evaluate the answer
            $evaluation = $this->evaluateAnswerWithAi($question, $userAnswer);

            $submission = QuizSubmission::create([
                'quiz_attempt_id' => $attempt->id,
                'quiz_question_id' => $questionId,
                'user_answer' => $userAnswer,
                'is_correct' => $evaluation['is_correct'],
                'ai_feedback' => $evaluation['feedback'],
                'ai_analysis' => $evaluation['analysis'],
            ]);

            if ($evaluation['is_correct']) {
                $correctCount++;
            }
        }

        // Calculate score
        $scorePercentage = ($correctCount / $totalQuestions) * 100;
        $isPassed = $scorePercentage >= 70;
        $xpEarned = round($scorePercentage / 10); // Variable XP based on performance

        // Update attempt
        $attempt->update([
            'completed_at' => now(),
            'score_percentage' => $scorePercentage,
            'xp_earned' => $xpEarned,
            'is_passed' => $isPassed,
        ]);

        // Award XP: 5 XP to solver
        $this->gamificationService->awardXp($attempt->user, 5);

        // Award 1 XP to creator if quiz is published
        if ($quiz->is_published && $quiz->creator) {
            $this->gamificationService->awardXp($quiz->creator, 1);
        }

        // Update quiz stats
        $quiz->increment('total_attempts');
        if ($isPassed) {
            $quiz->increment('success_count');
        }

        return [
            'success' => true,
            'score_percentage' => $scorePercentage,
            'correct_count' => $correctCount,
            'total_count' => $totalQuestions,
            'is_passed' => $isPassed,
            'xp_earned' => $xpEarned,
            'submissions' => $attempt->submissions()->with('question')->get(),
        ];
    }

    /**
     * Evaluate a single answer using AI
     */
    private function evaluateAnswerWithAi(QuizQuestion $question, string $userAnswer): array
    {
        $evaluationPrompt = <<<PROMPT
You are an expert quiz evaluator. Analyze the following answer submission:

Question: {$question->question_text}

Expected/Correct Answer: {$question->correct_answer}

Student's Answer: {$userAnswer}

Please evaluate if the student's answer is correct, acceptable, or incorrect. Accept answers that demonstrate understanding even if they're worded differently. Be fair and consider alternative valid answers.

Respond in JSON format:
{
    "is_correct": boolean,
    "confidence": 0-100,
    "explanation": "brief explanation",
    "feedback": "constructive feedback for student",
    "suggest_improvement": "if answer needs improvement"
}
PROMPT;

        try {
            $response = $this->aiService->callGemini($evaluationPrompt);
            $analysis = json_decode($response, true);

            return [
                'is_correct' => $analysis['is_correct'] ?? false,
                'feedback' => [
                    'explanation' => $analysis['explanation'] ?? '',
                    'feedback' => $analysis['feedback'] ?? '',
                    'suggestion' => $analysis['suggest_improvement'] ?? '',
                    'confidence' => $analysis['confidence'] ?? 0,
                ],
                'analysis' => $analysis,
            ];
        } catch (\Exception $e) {
            Log::error('AI answer evaluation failed', [
                'question_id' => $question->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to strict matching
            $isCorrect = strtolower(trim($userAnswer)) === strtolower(trim($question->correct_answer));

            return [
                'is_correct' => $isCorrect,
                'feedback' => [
                    'explanation' => 'AI evaluation unavailable',
                    'feedback' => 'Your answer has been evaluated.',
                    'suggestion' => '',
                    'confidence' => 50,
                ],
                'analysis' => [],
            ];
        }
    }

    /**
     * Approve quiz for publishing
     */
    public function approveQuiz(Quiz $quiz): bool
    {
        return $quiz->update([
            'is_published' => true,
            'approved_by_admin' => true,
            'status' => 'published',
        ]);
    }

    /**
     * Reject quiz with feedback
     */
    public function rejectQuiz(Quiz $quiz, string $feedback): bool
    {
        return $quiz->update([
            'status' => 'draft',
            'ai_analysis' => array_merge($quiz->ai_analysis ?? [], ['rejection_reason' => $feedback]),
        ]);
    }

    /**
     * Get quiz progress for a user
     */
    public function getUserQuizProgress(User $user)
    {
        return [
            'total_attempts' => QuizAttempt::where('user_id', $user->id)->count(),
            'quizzes_passed' => QuizAttempt::where('user_id', $user->id)->where('is_passed', true)->count(),
            'total_xp_earned' => QuizAttempt::where('user_id', $user->id)->sum('xp_earned'),
            'average_score' => round(QuizAttempt::where('user_id', $user->id)->avg('score_percentage'), 2),
        ];
    }

    /**
     * Get recommended quizzes for a user
     */
    public function getRecommendedQuizzes(User $user, int $limit = 5)
    {
        // Get user's attempted quizzes
        $attemptedQuizIds = QuizAttempt::where('user_id', $user->id)
            ->pluck('quiz_id')
            ->toArray();

        // Get user's preferred categories (from previous attempts)
        $preferredCategories = QuizAttempt::where('user_id', $user->id)
            ->join('quizzes', 'quiz_attempts.quiz_id', '=', 'quizzes.id')
            ->pluck('quizzes.category')
            ->toArray();

        // Recommend similar difficulty quizzes
        $query = Quiz::published();

        if (!empty($attemptedQuizIds)) {
            $query->whereNotIn('id', $attemptedQuizIds);
        }

        if (!empty($preferredCategories)) {
            $query->whereIn('category', $preferredCategories);
        }

        return $query->orderBy('total_attempts', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Build AI analysis prompt for quiz
     */
    private function buildQuizAnalysisPrompt(Quiz $quiz, $questions): string
    {
        $questionsText = $questions->map(function ($q) {
            return "Q: {$q->question_text}\nA: {$q->correct_answer}\nExplanation: {$q->answer_explanation}";
        })->implode("\n\n");

        return <<<PROMPT
Please analyze the following quiz for quality, fairness, and educational value:

Title: {$quiz->title}
Description: {$quiz->description}
Difficulty: {$quiz->difficulty_level}

Questions:
$questionsText

Please provide:
1. Overall quality assessment
2. Any potential issues or improvements
3. Clarity of questions (1-10)
4. Fairness and appropriateness
5. Whether the quiz is ready for publishing (approve/reject)

Respond in JSON format.
PROMPT;
    }

    /**
     * Parse AI analysis response
     */
    private function parseAiAnalysis(string $response): array
    {
        try {
            return json_decode($response, true);
        } catch (\Exception $e) {
            return [
                'raw_analysis' => $response,
                'parsed' => false,
            ];
        }
    }
}
