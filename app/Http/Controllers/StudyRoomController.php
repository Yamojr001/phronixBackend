<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\StudyProgress;
use App\Services\AiService;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class StudyRoomController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $timetable = $user->masterTimetable;
        
        $suggestedCourse = null;
        if ($timetable) {
            $dayName = now()->format('l'); // Monday, Tuesday, etc.
            $currentWeek = $timetable->current_week;
            $schedule = $timetable->weekly_schedule['week_' . $currentWeek][$dayName] ?? [];
            
            if (!empty($schedule)) {
                // Find first course in schedule
                $firstSlot = $schedule[0];
                $courseTitle = $firstSlot['course'] ?? null;
                if ($courseTitle) {
                    $suggestedCourse = $user->courses()->where('title', 'like', "%{$courseTitle}%")->first();
                }
            }
        }

        $courses = $user->courses()->whereNotNull('generated_handout')->get()
            ->map(function($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'code' => $course->code,
                ];
            });

        $payload = [
            'suggestedCourse' => $suggestedCourse,
            'courses' => $courses,
            'currentDay' => now()->format('l'),
            'currentWeek' => $timetable ? $timetable->current_week : 1,
        ];

        if (request()->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('StudyRoom/Index', $payload);
    }

    public function show(Course $course, AiService $aiService)
    {
        $user = Auth::user();
        if ($course->user_id !== $user->id) abort(403);

        $timetable = $user->masterTimetable;
        $weekNumber = $timetable ? $timetable->current_week : 1;
        $dayName = strtolower(now()->format('l'));

        $handout = json_decode($course->generated_handout, true);
        $handout = is_array($handout) ? $handout : [];
        $handoutDayName = ucfirst($dayName);
        
        // Find handout for today
        $todayHandout = null;
        if (isset($handout['weeks'])) {
            $handoutUpdated = false;

            foreach ($handout['weeks'] as $weekIndex => $week) {
                if ($week['week_number'] == $weekNumber) {
                    $todayHandout = $week['days'][$handoutDayName] ?? null;

                    if (is_array($todayHandout) && empty($todayHandout['reading_text'])) {
                        $dailyOutline = [
                            'focus' => (string) ($todayHandout['focus'] ?? ''),
                            'points' => array_values(array_filter((array) ($todayHandout['points'] ?? []))),
                            'tasks' => array_values(array_filter((array) ($todayHandout['tasks'] ?? []))),
                            'day' => $dayName,
                            'week_number' => $weekNumber,
                        ];

                        $readingText = $aiService->generateDailyReadingContent(
                            (string) ($course->full_content ?? ''),
                            $dailyOutline,
                            (string) $course->title,
                            (string) $course->code
                        );

                        if (!$readingText) {
                            $readingText = $this->extractLectureReadingText((string) ($course->full_content ?? ''), $todayHandout);
                            $todayHandout['reading_source'] = 'lecture_note_fallback';
                        } else {
                            $todayHandout['reading_source'] = 'ai_lecture_note';
                        }

                        $todayHandout['reading_text'] = $readingText;
                        $handout['weeks'][$weekIndex]['days'][$handoutDayName] = $todayHandout;
                        $handoutUpdated = true;
                    }

                    break;
                }
            }

            if ($handoutUpdated) {
                $course->update([
                    'generated_handout' => json_encode($handout),
                ]);
            }
        }

        $progress = null;
        if (!$todayHandout && isset($handout['weeks'])) {
            // Fallback: Find the most recent study day in this week or previous weeks
            foreach (range($weekNumber, 1) as $w) {
                foreach ($handout['weeks'] as $week) {
                    if ($week['week_number'] == $w) {
                        $days = ['Friday', 'Thursday', 'Wednesday', 'Tuesday', 'Monday'];
                        foreach ($days as $fallbackDay) {
                            if (isset($week['days'][$fallbackDay])) {
                                $todayHandout = $week['days'][$fallbackDay];
                                $dayName = strtolower($fallbackDay);
                                $weekNumber = $w;
                                $todayHandout['is_fallback'] = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        $progress = StudyProgress::firstOrCreate([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'week_number' => $weekNumber,
            'day_name' => $dayName,
        ]);

        $dailyProgress = $this->buildDailyProgress($progress, count($todayHandout['tasks'] ?? []));

        $payload = [
            'course' => $course,
            'todayHandout' => $todayHandout,
            'progress' => $progress,
            'dailyProgress' => $dailyProgress,
            'weekNumber' => $weekNumber,
            'dayName' => ucfirst($dayName),
        ];

        if (request()->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('StudyRoom/Show', $payload);
    }

    public function explain(Request $request, AiService $aiService)
    {
        $request->validate(['text' => 'required|string']);
        $explanation = $aiService->explainText($request->text);
        return response()->json(['explanation' => $explanation]);
    }

    public function generateTest(Course $course, AiService $aiService)
    {
        $user = Auth::user();
        $timetable = $user->masterTimetable;
        $weekNumber = $timetable ? $timetable->current_week : 1;
        $dayName = strtolower(now()->format('l'));

        $progress = StudyProgress::where([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'week_number' => $weekNumber,
            'day_name' => $dayName,
        ])->first();

        if ($progress && $progress->test_questions) {
            return response()->json(['questions' => array_values(array_filter($progress->test_questions))]);
        }

        $handout = json_decode($course->generated_handout, true);
        $todayHandout = null;
        $handoutDayName = ucfirst($dayName);
        if (isset($handout['weeks'])) {
            foreach ($handout['weeks'] as $week) {
                if ($week['week_number'] == $weekNumber) {
                    $todayHandout = $week['days'][$handoutDayName] ?? null;
                    break;
                }
            }
        }

        // Use focus and points from handout to generate test
        $content = "Course: {$course->title}\n";
        if ($todayHandout) {
            $content .= "Focus: {$todayHandout['focus']}\n";
            $content .= "Points: " . implode("\n", $todayHandout['points']);
        } else {
            $content .= substr($course->full_content, 0, 5000);
        }

        $testData = $aiService->generateMiniTest($content);
        
        $questions = array_values(array_filter($testData['questions'] ?? [], fn ($question) => is_array($question) && !empty($question['question'])));
        if ($progress) {
            $progress->update(['test_questions' => $questions]);
        }

        return response()->json(['questions' => $questions]);
    }

    public function submitTest(Request $request, GamificationService $gamificationService)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'answers' => 'required|array',
            'week_number' => 'required|integer',
            'day_name' => 'required|string',
        ]);

        $progress = StudyProgress::where([
            'user_id' => Auth::id(),
            'course_id' => $request->course_id,
            'week_number' => $request->week_number,
            'day_name' => strtolower($request->day_name),
        ])->firstOrFail();

        // Simple grading for objective questions
        $score = 0;
        $questions = $progress->test_questions ?? [];
        $total = count($questions);
        
        if ($total === 0) return response()->json(['score' => 0, 'passed' => false]);

        $failedQuestions = [];

        foreach ($questions as $index => $q) {
            $isCorrect = false;
            if ($q['type'] === 'objective') {
                if (($request->answers[$index] ?? null) == $q['correct_answer_index']) {
                    $isCorrect = true;
                    $score++;
                }
            } else {
                // For essay and fill-in, we just give credit for providing an answer for now
                if (!empty($request->answers[$index])) {
                    $isCorrect = true;
                    $score++;
                }
            }

            if (!$isCorrect) {
                $failedQuestions[] = [
                    'question' => $q['question'],
                    'correct_answer' => $q['correct_answer'] ?? ($q['options'][$q['correct_answer_index']] ?? 'N/A'),
                    'context' => $q['context'] ?? 'Review the reading material for this topic.',
                ];
            }
        }

        $percentage = ($score / $total) * 100;
        $passed = $percentage >= 70;
        $isFirstSubmission = $progress->test_score === null;

        $progress->update([
            'test_score' => $percentage,
            'test_passed' => $passed,
        ]);

        $dailyProgress = $this->buildDailyProgress($progress->refresh(), count($progress->completed_tasks ?? []) + max(0, count($questions)));

        if ($isFirstSubmission) {
            $gamificationService->recordTestResult(Auth::user(), $percentage);
        }

        return response()->json([
            'score' => $percentage,
            'passed' => $passed,
            'dailyProgress' => $dailyProgress,
            'failedQuestions' => $failedQuestions,
        ]);
    }

    public function markReading(Request $request, GamificationService $gamificationService)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'week_number' => 'required|integer|min:1',
            'day_name' => 'required|string',
            'minutes' => 'nullable|integer|min:0|max:240',
            'completed' => 'nullable|boolean',
        ]);

        $course = Course::findOrFail($request->course_id);
        if ($course->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $dayName = strtolower($request->day_name);
        $minutes = (int) ($request->minutes ?? 15);
        $completed = $request->boolean('completed', true);

        $progress = StudyProgress::firstOrCreate([
            'user_id' => Auth::id(),
            'course_id' => $course->id,
            'week_number' => (int) $request->week_number,
            'day_name' => $dayName,
        ]);

        $progress->reading_minutes = (int) $progress->reading_minutes + $minutes;
        if ($completed) {
            $progress->reading_completed = true;
            $progress->reading_completed_at = $progress->reading_completed_at ?? now();
        }
        $progress->save();

        $gamificationService->recordStudyMinutes(Auth::user(), $minutes);

        $dailyProgress = $this->buildDailyProgress($progress->refresh(), count($this->getHandoutTasks($course, (int) $request->week_number, $dayName)));

        return response()->json([
            'success' => true,
            'progress' => $progress,
            'dailyProgress' => $dailyProgress,
        ]);
    }

    public function toggleTask(Request $request, GamificationService $gamificationService)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'task' => 'required|string',
            'week_number' => 'required|integer',
            'day_name' => 'required|string',
        ]);

        $progress = StudyProgress::firstOrCreate([
            'user_id' => Auth::id(),
            'course_id' => $request->course_id,
            'week_number' => $request->week_number,
            'day_name' => strtolower($request->day_name),
        ]);

        $completedTasks = $progress->completed_tasks ?? [];
        $wasCompleted = in_array($request->task, $completedTasks, true);

        if ($wasCompleted) {
            $completedTasks = array_values(array_diff($completedTasks, [$request->task]));
        } else {
            $completedTasks[] = $request->task;
        }

        $progress->update(['completed_tasks' => $completedTasks]);

        if (!$wasCompleted) {
            $gamificationService->recordTaskCompletion(Auth::user());
        }

        $dailyProgress = $this->buildDailyProgress($progress->refresh(), count($this->getHandoutTasks(Course::findOrFail($request->course_id), (int) $request->week_number, strtolower($request->day_name))));

        return response()->json([
            'completed_tasks' => $completedTasks,
            'dailyProgress' => $dailyProgress,
        ]);
    }

    private function buildDailyProgress(StudyProgress $progress, int $taskCount): array
    {
        $completedTasksCount = count($progress->completed_tasks ?? []);
        $tasksScore = $taskCount > 0 ? min(100, (int) round(($completedTasksCount / $taskCount) * 100)) : ($completedTasksCount > 0 ? 100 : 0);
        $readingScore = $progress->reading_completed ? 100 : min(100, (int) round(($progress->reading_minutes / 15) * 100));
        $testScore = $progress->test_score !== null ? 100 : 0;

        $overall = (int) round(($readingScore * 0.45) + ($tasksScore * 0.35) + ($testScore * 0.20));

        return [
            'score' => $overall,
            'reading' => $readingScore,
            'tasks' => $tasksScore,
            'test' => $testScore,
            'reading_minutes' => (int) $progress->reading_minutes,
            'reading_completed' => (bool) $progress->reading_completed,
            'tasks_completed' => $completedTasksCount,
            'tasks_total' => $taskCount,
            'test_score' => $progress->test_score,
            'test_passed' => (bool) $progress->test_passed,
        ];
    }

    /**
     * Fallback: build reading text from lecture notes when AI daily generation is unavailable.
     */
    private function extractLectureReadingText(string $fullContent, array $todayHandout): string
    {
        $content = trim($fullContent);
        if ($content === '') {
            return '';
        }

        $paragraphs = preg_split('/\n\s*\n+/', $content) ?: [];
        $paragraphs = array_values(array_filter(array_map(function ($paragraph) {
            $normalized = preg_replace('/\s+/', ' ', trim((string) $paragraph));
            return $normalized;
        }, $paragraphs), fn ($paragraph) => mb_strlen($paragraph) >= 80));

        if (empty($paragraphs)) {
            $sentences = preg_split('/(?<=[.!?])\s+/', preg_replace('/\s+/', ' ', $content)) ?: [];
            $chunks = [];
            $chunk = '';

            foreach ($sentences as $sentence) {
                $sentence = trim((string) $sentence);
                if ($sentence === '') {
                    continue;
                }

                $candidate = trim($chunk . ' ' . $sentence);
                if (mb_strlen($candidate) > 700 && $chunk !== '') {
                    $chunks[] = $chunk;
                    $chunk = $sentence;
                } else {
                    $chunk = $candidate;
                }
            }

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            $paragraphs = $chunks;
        }

        if (empty($paragraphs)) {
            return '';
        }

        $keywords = $this->extractReadingKeywords($todayHandout);
        $scored = [];

        foreach ($paragraphs as $index => $paragraph) {
            $score = 0;
            $lowerParagraph = mb_strtolower($paragraph);

            foreach ($keywords as $keyword) {
                $score += mb_substr_count($lowerParagraph, $keyword);
            }

            $scored[] = [
                'index' => $index,
                'paragraph' => $paragraph,
                'score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $selected = [];
        $charLimit = 4200;
        $charCount = 0;

        foreach ($scored as $entry) {
            if ($entry['score'] <= 0 && !empty($selected)) {
                break;
            }

            $nextLength = mb_strlen($entry['paragraph']);
            if ($charCount + $nextLength > $charLimit && !empty($selected)) {
                break;
            }

            $selected[] = $entry;
            $charCount += $nextLength;

            if (count($selected) >= 5) {
                break;
            }
        }

        if (empty($selected)) {
            $selected = array_map(fn ($paragraph, $index) => [
                'index' => $index,
                'paragraph' => $paragraph,
                'score' => 0,
            ], array_slice($paragraphs, 0, 4), array_keys(array_slice($paragraphs, 0, 4)));
        }

        usort($selected, fn ($a, $b) => $a['index'] <=> $b['index']);

        return implode("\n\n", array_map(fn ($entry) => $entry['paragraph'], $selected));
    }

    private function extractReadingKeywords(array $todayHandout): array
    {
        $stopWords = [
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'into', 'your', 'you', 'are', 'was', 'were',
            'have', 'has', 'had', 'will', 'shall', 'should', 'can', 'could', 'about', 'than', 'then', 'they',
            'them', 'their', 'what', 'when', 'where', 'which', 'while', 'through', 'each', 'only', 'also',
            'focus', 'study', 'task', 'daily', 'week', 'read', 'reading', 'review', 'prepare', 'learn'
        ];

        $pool = [];
        $pool[] = (string) ($todayHandout['focus'] ?? '');
        foreach ((array) ($todayHandout['points'] ?? []) as $point) {
            $pool[] = (string) $point;
        }

        $text = mb_strtolower(implode(' ', $pool));
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $parts = preg_split('/\s+/', (string) $text) ?: [];

        $keywords = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || mb_strlen($part) < 4 || in_array($part, $stopWords, true)) {
                continue;
            }
            $keywords[$part] = true;
        }

        return array_keys($keywords);
    }

    private function getHandoutTasks(Course $course, int $weekNumber, string $dayName): array
    {
        $handout = json_decode($course->generated_handout, true);

        if (!isset($handout['weeks'])) {
            return [];
        }

        foreach ($handout['weeks'] as $week) {
            if ((int) ($week['week_number'] ?? 0) === $weekNumber) {
                $handoutDayName = ucfirst($dayName);
                return $week['days'][$handoutDayName]['tasks'] ?? [];
            }
        }

        return [];
    }
}
