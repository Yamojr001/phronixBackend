<?php

namespace App\Jobs;

use App\Models\StudySummary;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateAiSummaryJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // 10 minutes max for the whole job

    protected $studySummary;
    protected $extractedText;

    /**
     * Create a new job instance.
     */
    public function __construct(StudySummary $studySummary, string $extractedText)
    {
        $this->studySummary = $studySummary;
        $this->extractedText = $extractedText;
    }

    /**
     * Execute the job.
     */
    public function handle(AiService $aiService): void
    {
        try {
            Log::info("Starting background segmented AI summary generation for summary ID: {$this->studySummary->id}");
            $this->studySummary->update(['status' => 'processing', 'progress' => 0]);
            
            // Split text into chunks of roughly 50,000 characters to process piece by piece
            $chunks = str_split($this->extractedText, 50000); 
            $totalChunks = count($chunks);
            
            $finalSummary = "";
            
            foreach ($chunks as $index => $chunk) {
                // We use the existing AI service to generate key points for this specific chunk
                $chunkSummary = $aiService->generateExamKeyPoints($chunk);
                
                if (!$chunkSummary) {
                    $chunkSummary = "*(Failed to generate summary for this segment)*";
                }
                
                $finalSummary .= "\n\n### Segment " . ($index + 1) . " of {$totalChunks}\n\n" . $chunkSummary;
                
                // Update progress dynamically so the frontend can poll it
                $progress = (int) ((($index + 1) / $totalChunks) * 100);
                $this->studySummary->update([
                    'summary_content' => trim($finalSummary),
                    'progress' => $progress
                ]);
                
                // Let the AI rest for 4 seconds between chunks to avoid rate limits
                if ($index < $totalChunks - 1) {
                    sleep(4);
                }
            }
            
            $this->studySummary->update([
                'status' => 'completed',
                'progress' => 100
            ]);
            Log::info("Successfully completed segmented AI summary ID: {$this->studySummary->id}");
            
        } catch (\Exception $e) {
            Log::error("Failed to generate background summary ID {$this->studySummary->id}: " . $e->getMessage());
            $this->studySummary->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }
}
