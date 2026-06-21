<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use App\Models\StudySummary;
use App\Jobs\GenerateAiSummaryJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Smalot\PdfParser\Parser;

class StudyAideController extends Controller
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Display API endpoint info (called when someone navigates via GET).
     */
    public function info()
    {
        return response()->json([
            'message' => 'To summarize documents, use POST /api/study-aide/summarize with multipart/form-data containing files and optional course_code.',
            'endpoints' => [
                'POST /api/study-aide/summarize' => 'Generate a summary from uploaded documents',
                'GET /api/study-aide/summaries' => 'Fetch your summary history',
                'GET /api/study-aide/summaries/{id}' => 'Fetch a specific summary',
                'DELETE /api/study-aide/summaries/{id}' => 'Delete a summary from history',
            ]
        ], 200);
    }

    /**
     * Extract key exam points from an uploaded document.
     */
    public function summarize(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:20480|mimes:pdf,png,jpg,jpeg,txt,docx,pptx,ppt',
            'course_code' => 'nullable|string|max:20',
        ]);

        try {
            $extractedText = '';
            $files = $request->file('files');
            
            foreach ($files as $index => $file) {
                $filePath = $file->getRealPath();
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = $file->getMimeType();
                $fileName = $file->getClientOriginalName();

                $extractedText .= "\n\n--- DOCUMENT " . ($index + 1) . ": {$fileName} ---\n\n";

                if ($extension === 'txt') {
                    $extractedText .= file_get_contents($filePath);
                } elseif (in_array($extension, ['png', 'jpg', 'jpeg'])) {
                    $extractedText .= $this->aiService->extractTextFromImage($filePath, $mimeType);
                } elseif ($extension === 'pdf') {
                    try {
                        $parser = new Parser();
                        $pdf = $parser->parseFile($filePath);
                        $text = $pdf->getText();
                        
                        // If PDF extraction is too thin, fallback to Vision
                        if (strlen(trim($text)) < 150) {
                            $text = $this->aiService->extractTextFromImage($filePath, 'application/pdf');
                        }
                        $extractedText .= $text;
                    } catch (\Exception $e) {
                        $extractedText .= $this->aiService->extractTextFromImage($filePath, 'application/pdf');
                    }
                } elseif ($extension === 'docx') {
                    $zip = new \ZipArchive;
                    if ($zip->open($filePath) === true) {
                        if (($indexDoc = $zip->locateName('word/document.xml')) !== false) {
                            $data = $zip->getFromIndex($indexDoc);
                            $extractedText .= strip_tags(str_replace(['<w:p', '<w:br', '<w:tab'], [' <w:p', ' <w:br', ' <w:tab'], $data));
                        }
                        $zip->close();
                    }
                } elseif ($extension === 'pptx') {
                    $zip = new \ZipArchive;
                    if ($zip->open($filePath) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $entry = $zip->getNameIndex($i);
                            if (strpos($entry, 'ppt/slides/slide') !== false && strpos($entry, '.xml') !== false) {
                                $slideXml = $zip->getFromName($entry);
                                $extractedText .= strip_tags($slideXml) . " ";
                            }
                        }
                        $zip->close();
                    }
                } elseif ($extension === 'ppt' || $extension === 'doc') {
                    $content = file_get_contents($filePath);
                    $extractedText .= preg_replace('/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/', '', $content) . " ";
                }
            }

            if (empty(trim($extractedText))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not extract any readable text from the provided file.'
                ], 422);
            }

            // Save to history as pending
            $studySummary = StudySummary::create([
                'user_id' => Auth::id(),
                'course_code' => $request->course_code,
                'files' => array_map(fn($f) => $f->getClientOriginalName(), $files),
                'summary_content' => null,
                'status' => 'pending',
                'progress' => 0
            ]);

            // Dispatch background segmented generation
            GenerateAiSummaryJob::dispatch($studySummary, $extractedText);

            return response()->json([
                'success' => true,
                'message' => 'Your document has been queued for background processing. The summary will generate in segments.',
                'course_code' => $request->course_code,
                'id' => $studySummary->id,
            ]);

        } catch (\Exception $e) {
            Log::error('StudyAide Summarization Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during processing: ' . $e->getMessage()
            ], 500);
        }
    }

    public function history()
    {
        try {
            if (!Auth::check()) {
                Log::warning('StudyAide::history() called without authentication');
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $summaries = StudySummary::where('user_id', Auth::id())
                ->latest()
                ->paginate(12);

            return response()->json($summaries);
        } catch (\Exception $e) {
            Log::error('StudyAide History Fetch Failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Failed to fetch summary history',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(StudySummary $studySummary)
    {
        if ($studySummary->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($studySummary);
    }

    public function destroy(StudySummary $studySummary)
    {
        if ($studySummary->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $studySummary->delete();

        return response()->json(['message' => 'History item deleted']);
    }
}
