<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Test;
use App\Http\Requests\Api\StoreCourseRequest;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Smalot\PdfParser\Parser;
use App\Support\TestType;

class CourseController extends Controller
{
    /**
     * Display a listing of the user's courses.
     */
    public function index()
    {
        return response()->json([
            'courses' => Auth::user()->courses()
                            ->where('semester_id', Auth::user()->current_semester_id)
                            ->orderBy('created_at', 'desc')
                            ->get(),
            'flash' => [ 'message' => session('message'), 'error' => session('error') ]
        ]);
    }

    /**
     * Store a newly created course in storage.
     */
    public function store(StoreCourseRequest $request)
    {
        $user = Auth::user();

        // 1. Profile Check: Ensure school and department are set
        if (empty($user->school) || empty($user->department)) {
            return response()->json([
                'error' => 'Please update your school and department in your profile before uploading a course.'
            ], 422);
        }

        $validated = $request->validated();
        
        $course = $user->courses()->create([
            'title' => $validated['title'],
            'code' => $validated['code'],
            'credit_unit' => $validated['credit_unit'],
            'semester_id' => $user->current_semester_id,
            'file_path' => null, // Files are not stored permanently for now, just text
            'status' => 'Analyzing Syllabus...',
        ]);

        // 2. CourseContent Uniqueness & Storage
        $semester = $user->currentSemester;
        $year = (int)date('Y'); // Default to current calendar year
        
        // Try to extract year from semester name (e.g., "2023/2024" -> 2023)
        if ($semester && preg_match('/\b(20\d{2})\b/', $semester->name, $matches)) {
            $year = (int)$matches[1];
        }

        \App\Models\CourseContent::firstOrCreate(
            [
                'school' => $user->school,
                'department' => $user->department,
                'year' => $year,
                'course_code' => $validated['code'],
            ],
            [
                'content' => $validated['syllabus_text'],
            ]
        );

        try {
            $aiService = new AiService();
            $topics = $aiService->extractTopicsFromText($validated['syllabus_text']);

            if ($topics) {
                $course->update(['topics' => $topics, 'status' => 'Pre-Test Needed']);
            } else {
                $course->update(['status' => 'AI Analysis Failed']);
            }
        } catch (\Exception $e) {
            $course->update(['status' => 'AI Analysis Failed']);
            \Log::error('AI Service Exception in store(): ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Course added and analysis complete!',
            'course' => $course
        ], 201);
    }

    /**
     * Extract text from various file formats.
     */
    /**
     * Extract text from various file formats.
     */
    public function extractText(\App\Http\Requests\Api\ExtractTextRequest $request)
    {
        $filePath = null;
        $mimeType = null;
        $extension = '';
        $fileName = '';

        if ($request->filled('base64_file')) {
            $base64Data = $request->input('base64_file');
            // Remove data URI prefix if present
            if (preg_match('/^data:(\w+\/\w+);base64,/', $base64Data, $matches)) {
                $mimeType = $matches[1];
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $extension = strtolower(explode('/', $mimeType)[1] ?? 'pdf');
            } else {
                $mimeType = $request->input('mime_type', 'application/pdf');
                $extension = 'pdf';
            }

            $decodedData = base64_decode($base64Data);
            $tempPath = tempnam(sys_get_temp_dir(), 'upl');
            file_put_contents($tempPath, $decodedData);
            
            $filePath = $tempPath;
            $fileName = $request->input('file_name', 'upload.pdf');
        } else {
            $file = $request->file('file');
            $filePath = $file->getRealPath();
            $mimeType = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());
            $fileName = $file->getClientOriginalName();
        }

        $realMimeType = mime_content_type($filePath) ?: $mimeType;
        $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'ppt', 'pptx', 'pptm', 'docx'];
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file extension: ' . $extension
            ], 422);
        }

        $extractedText = '';
        $pageCount = 0;
        $maxExtractedChars = 250000;
        
        try {
            if ($extension === 'txt' || $mimeType === 'text/plain') {
                $extractedText = file_get_contents($filePath);
                $pageCount = max(1, ceil(str_word_count($extractedText) / 250));
            } elseif (in_array($extension, ['png', 'jpg', 'jpeg'])) {
                $aiService = new AiService();
                $extractedText = $aiService->extractTextFromImage($filePath, $mimeType);
                $pageCount = 1;
            } elseif ($extension === 'pdf') {
                try {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($filePath);
                    $extractedText = $pdf->getText();
                    $pageCount = max(1, count($pdf->getPages()));
                    
                    if (strlen(trim($extractedText)) < 150) {
                        $aiService = new AiService();
                        $extractedText = $aiService->extractTextFromImage($filePath, 'application/pdf');
                    }
                } catch (\Exception $e) {
                    \Log::info("PDF Parser failed for {$fileName}, falling back to Vision: " . $e->getMessage());
                    $aiService = new AiService();
                    $extractedText = $aiService->extractTextFromImage($filePath, 'application/pdf');
                    $pageCount = 1;
                }
            } elseif (in_array($extension, ['ppt', 'pptx'])) {
                if ($extension === 'pptx') {
                    $zip = new \ZipArchive;
                    if ($zip->open($filePath) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $entry = $zip->getNameIndex($i);
                            if (strpos($entry, 'ppt/slides/slide') !== false && strpos($entry, '.xml') !== false) {
                                $slideXml = $zip->getFromName($entry);
                                $extractedText .= strip_tags($slideXml) . " ";
                                $pageCount++;
                            }
                        }
                        $zip->close();
                        $pageCount = max(1, $pageCount);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Legacy .ppt files are not supported. Please convert to .pptx or PDF.'
                    ], 422);
                }
            } elseif ($extension === 'docx') {
                $zip = new \ZipArchive;
                if ($zip->open($filePath) === true) {
                    if (($index = $zip->locateName('word/document.xml')) !== false) {
                        $data = $zip->getFromIndex($index);
                        $extractedText = strip_tags(str_replace(['<w:p', '<w:br', '<w:tab'], [' <w:p', ' <w:br', ' <w:tab'], $data));
                        $pageCount = max(1, ceil(strlen($extractedText) / 3000));
                    }
                    $zip->close();
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to open DOCX file.'
                    ], 422);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Unsupported file type: {$extension}. Supported: pdf, png, jpg, txt, pptx."
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'text' => trim(substr($extractedText, 0, $maxExtractedChars)),
                'pageCount' => $pageCount
            ]);
        } catch (\Exception $e) {
            \Log::error("Extraction failed: " . $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'Failed to extract text: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified course details.
     */
    public function show(Course $course)
    {
        // Security: Ensure the logged-in user owns this course.
        Gate::authorize('view', $course);

        // Eager load the test history for this specific course, ordered by newest first.
        $course->load(['tests' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        $courseData = $course->toArray();
        $courseData['isHandoutGenerated'] = !empty($course->generated_handout);
        $courseData['generatedHandout'] = !empty($course->generated_handout) ? json_decode($course->generated_handout, true) : null;

        return response()->json([
            'course' => $courseData,
        ]);
    }

    /**
     * Show the pre-test for a specific course.
     */
    /**
     * Show the pre-test for a specific course.
     */
    public function showTest(Course $course)
    {
        Gate::authorize('view', $course);
        
        $content = $course->full_content;
        if (empty($content)) {
            return response()->json(['error' => 'Course content is missing. Please try re-uploading the course.'], 404);
        }
        
        $aiService = new AiService(config('services.gemini.api_key'));
        $testData = $aiService->generateTestFromContent($content);

        if (!$testData || !isset($testData['questions'])) {
            return response()->json(['error' => 'The AI failed to generate a test. Please try again later.'], 500);
        }
        
        $testId = \Illuminate\Support\Str::uuid()->toString();
        \Illuminate\Support\Facades\Cache::put('test_'.$testId, [
            'questions' => $testData['questions'],
            'topics' => $course->topics
        ], now()->addHour());

        return response()->json([
            'test_id' => $testId,
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'code' => $course->code,
            ],
            'questions' => array_map(fn($q) => ['question' => $q['question'], 'options' => $q['options']], $testData['questions']),
        ]);
    }

    /**
     * Store and grade the results of the submitted test.
     */
    public function storeTest(Request $request, Course $course)
    {
        Gate::authorize('update', $course);
        
        $testId = $request->input('test_id');
        $storedData = \Illuminate\Support\Facades\Cache::get('test_'.$testId);
        
        if (!$storedData) {
            return response()->json(['error' => 'Test session expired or invalid.'], 422);
        }

        $correctTestData = $storedData['questions'];
        $courseTopics = $storedData['topics'];

        $userAnswers = $request->input('answers');
        if (!is_array($userAnswers) || count($userAnswers) !== count($correctTestData)) {
            return response()->json(['error' => 'Invalid submission. Please answer all questions.'], 422);
        }

        $totalQuestions = count($correctTestData);
        $correctAnswersCount = 0;
        $weakTopics = [];
        $review = [];

        foreach ($correctTestData as $index => $questionData) {
            $correctIndex = $questionData['correct_answer_index'];
            $userAnswerIndex = $userAnswers[$index] ?? null;
            $correctAnswer = $questionData['options'][$correctIndex] ?? null;
            $userAnswerText = ($userAnswerIndex !== null && isset($questionData['options'][$userAnswerIndex])) ? $questionData['options'][$userAnswerIndex] : null;
            $isCorrect = ($userAnswerIndex !== null && (int)$userAnswerIndex === (int)$correctIndex);

            $review[] = [
                'question' => $questionData['question'],
                'options' => $questionData['options'],
                'correct_answer_index' => $correctIndex,
                'correct_answer' => $correctAnswer,
                'user_answer_index' => $userAnswerIndex,
                'user_answer' => $userAnswerText,
                'is_correct' => $isCorrect,
            ];

            if ($isCorrect) {
                $correctAnswersCount++;
            } else {
                if (count($courseTopics) > 0) {
                    $topicIndex = floor($index / ($totalQuestions / count($courseTopics)));
                    if (isset($courseTopics[$topicIndex])) {
                        $weakTopics[] = $courseTopics[$topicIndex];
                    }
                }
            }
        }

        $score = ($totalQuestions > 0) ? round(($correctAnswersCount / $totalQuestions) * 100) : 0;
        $uniqueWeakTopics = array_values(array_unique($weakTopics));

        $test = Test::create([
            'user_id' => Auth::id(), 
            'course_id' => $course->id, 
            'type' => TestType::PRE_TEST,
            'score' => $score, 
            'weak_topics' => $uniqueWeakTopics,
        ]);

        $course->update(['status' => 'In Progress', 'progress' => $score]);
        \Illuminate\Support\Facades\Cache::forget('test_'.$testId);

        return response()->json([
            'message' => 'Test submitted successfully',
            'test' => $test,
            'test_id' => $test->id,
            'test_result' => $test,
            'review' => $review,
        ]);
    }

    /**
     * Retry AI analysis for a course syllabus.
     */
    public function retryAnalysis(Course $course)
    {
        Gate::authorize('update', $course);

        $content = \App\Models\CourseContent::where([
            'school' => Auth::user()->school,
            'department' => Auth::user()->department,
            'course_code' => $course->code,
        ])->value('content');

        if (!$content) {
            return response()->json(['error' => 'Syllabus content not found. Please re-upload the course.'], 404);
        }

        $course->update(['status' => 'Analyzing Syllabus...']);

        try {
            $aiService = new AiService();
            $topics = $aiService->extractTopicsFromText($content);

            if ($topics) {
                $course->update(['topics' => $topics, 'status' => 'Pre-Test Needed']);
                return response()->json(['message' => 'AI Analysis successful! You can now take the pre-test.', 'course' => $course]);
            } else {
                $course->update(['status' => 'AI Analysis Failed']);
                return response()->json(['error' => 'AI Analysis failed again. Please try in a few moments.'], 500);
            }
        } catch (\Exception $e) {
            $course->update(['status' => 'AI Analysis Failed']);
            \Log::error('AI Service Retry Exception: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred during AI analysis. Please try again.'], 500);
        }
    }
}