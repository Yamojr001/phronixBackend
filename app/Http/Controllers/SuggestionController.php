<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Suggestion;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use League\CommonMark\CommonMarkConverter;

class SuggestionController extends Controller
{
    public function show(Request $request, Course $course)
    {
        Gate::authorize('view', $course);

        $latestTest = $course->tests()->latest()->first();
        $suggestion = $course->suggestion()->latest()->first();
        $weakTopics = $latestTest ? $latestTest->weak_topics : [];

        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json([
                'course' => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                ],
                'suggestion' => $suggestion ? $suggestion->content : null,
                'weak_areas' => $weakTopics,
                'recommended_topics' => $weakTopics,
            ]);
        }

        return Inertia::render('Suggestion/Show', [
            'course' => $course,
            'weakTopics' => $weakTopics,
            'suggestion' => $suggestion,
            'flash' => [ // Pass flash messages to the component
                'error' => session('error'),
            ]
        ]);
    }

    /**
     * Generate a new study guide suggestion.
     */
    public function generate(Request $request, Course $course) // <-- CORRECTED: Removed AiService from here
    {
        Gate::authorize('update', $course);

        $latestTest = $course->tests()->latest()->first();
        $weakTopics = $latestTest ? $latestTest->weak_topics : [];

        if (empty($weakTopics)) {
            if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                return response()->json(['error' => 'No weak topics found to generate a study guide.'], 400);
            }
            return back()->with('error', 'No weak topics found to generate a study guide.');
        }

        // =======================================================
        // THE FIX: Manually instantiate the AiService here, just like
        // we do in the CourseController.
        // =======================================================
        $aiService = new AiService(config('services.gemini.api_key'));
        
        $content = $course->full_content;
        if (empty($content)) {
            if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                return response()->json(['error' => 'Course content is missing. Please try re-uploading the course.'], 400);
            }
            return back()->with('error', 'Course content is missing. Please try re-uploading the course.');
        }

        $lockKey = 'idempotency:suggestion-generate:' . $request->user()->id . ':' . $course->id;
        $lock = Cache::lock($lockKey, 30);
        if (!$lock->get()) {
            if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                return response()->json(['error' => 'A study guide generation is already in progress. Please wait and retry.'], 409);
            }
            return back()->with('error', 'A study guide generation is already in progress. Please wait and retry.');
        }

        try {
            $markdownContent = $aiService->generateStudyGuide($content, $weakTopics);
        } finally {
            optional($lock)->release();
        }

        if (!$markdownContent) {
            if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                return response()->json(['error' => 'The AI failed to generate a study guide at this time. Please try again later.'], 500);
            }
            return back()->with('error', 'The AI failed to generate a study guide at this time. Please try again later.');
        }

        // Create or update the suggestion for this course
        $suggestion = Suggestion::updateOrCreate(
            ['course_id' => $course->id, 'user_id' => $request->user()->id],
            ['weak_topics' => $weakTopics, 'content' => $markdownContent]
        );

        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json([
                'message' => 'Study guide generated successfully.',
                'suggestion' => $suggestion->content,
            ]);
        }

        return redirect()->route('suggestion.show', $course->id);
    }

    public function download(Suggestion $suggestion)
    {
        Gate::authorize('view', $suggestion->course);

        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $html = $converter->convert($suggestion->content);

        // A simple wrapper to make the PDF look a bit nicer
        $pdfHtml = "<html><head><style>body { font-family: sans-serif; } h1, h2, h3 { color: #0a2540; } code { background: #f0f2f5; padding: 2px 4px; border-radius: 4px; } ul { list-style-type: disc; margin-left: 20px; }</style></head><body>" . $html . "</body></html>";

        $pdf = Pdf::loadHTML($pdfHtml);
        return $pdf->download('Phronix AI-Study-Guide-' . $suggestion->course->code . '.pdf');
    }
}