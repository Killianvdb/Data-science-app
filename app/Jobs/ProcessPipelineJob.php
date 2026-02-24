<?php

namespace App\Jobs;

use App\Models\PipelineJob;
use App\Models\User;
use App\Services\DataCleaningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 min max
    public int $tries   = 1;   // no retries — uploads are expensive

    public function __construct(
        private readonly int    $pipelineJobId,
        private readonly int    $userId,
        private readonly string $mainFilePath,
        private readonly array  $referenceFiles,
        private readonly string $pipelineMode,
        private readonly array  $options,          // rules_file, no_llm_enricher, column_types
    ) {}

    public function handle(DataCleaningService $cleaningService): void
    {
        $job = PipelineJob::findOrFail($this->pipelineJobId);

        try {
            // ── Step: cleaning ────────────────────────────────────────────────
            $job->setStep('cleaning');

            if ($this->pipelineMode === 'clean_only' || empty($this->referenceFiles)) {
                $result = $cleaningService->cleanUploadedFile(
                    $this->mainFilePath,
                    $this->options
                );
            } else {
                // ── Step: cross referencing ───────────────────────────────────
                $job->setStep('cross_referencing');

                $result = $cleaningService->runFullPipeline(
                    $this->mainFilePath,
                    $this->referenceFiles,
                    $this->options
                );

                // ── Step: enriching (already done inside runFullPipeline) ─────
                $job->setStep('enriching');
            }

            // ── Step: generating PDF report ───────────────────────────────────
            $pdfUrl = null;
            if (!empty($result['report_file']) && file_exists($result['report_file'])) {
                $job->setStep('generating_report');

                try {
                    $pdfPath = $cleaningService->generatePdfReport(
                        $result['report_file'],
                        $this->userId
                    );
                    if ($pdfPath && file_exists($pdfPath)) {
                        $result['report_pdf'] = $pdfPath;
                        $pdfUrl = route('datasets.download', [
                            'filename' => basename($pdfPath),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('[PipelineJob] PDF generation failed', ['error' => $e->getMessage()]);
                }
            }

            // ── Build download URLs ───────────────────────────────────────────
            $downloadUrls = [];
            $fileMap = [
                'cleaned_file_path' => 'cleaned',
                'enriched_file'     => 'enriched',
                'report_file'       => 'report',
            ];
            foreach ($fileMap as $key => $label) {
                if (!empty($result[$key])) {
                    $downloadUrls[$label] = route('datasets.download', [
                        'filename' => basename($result[$key]),
                    ]);
                }
            }
            if ($pdfUrl) {
                $downloadUrls['report_pdf'] = $pdfUrl;
            }

            // ── Increment usage ───────────────────────────────────────────────
            User::where('id', $this->userId)->increment('files_used_this_month', 1);

            // ── Mark done ─────────────────────────────────────────────────────
            $job->update([
                'status'       => 'done',
                'current_step' => 'done',
                'progress_pct' => 100,
                'result_json'  => json_encode([
                    'data'                  => $result,
                    'download_urls'         => $downloadUrls,
                    'pipeline_mode'         => $this->pipelineMode,
                    'context_rules_applied' => !empty($this->options['rules_file']),
                ]),
            ]);

        } catch (\Throwable $e) {
            Log::error('[PipelineJob] Pipeline failed', [
                'job_id' => $this->pipelineJobId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            $job->fail($e->getMessage());
        }
    }
}