<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

/**
 * DataCleaningService v3.0
 * ========================
 * Pipeline order (correct) :
 *   1. cross_reference.py  — merge all files + dedup on full dataset + validate + enrich
 *   2. data_cleaner.py     — sanitize (dates, prices, negatives) on merged+deduped data
 *
 * Why this order?
 *   - Deduplication on merged data catches inter-file duplicates that per-file cleaning misses
 *   - Validation runs on the complete picture, not partial data
 *   - Cleaning standardizes formats after the full dataset is assembled
 */
class DataCleaningService
{
    protected string $containerName  = 'datasci-python';
    protected string $sharedDataPath = '/shared_data';
    protected string $cleanerScript  = '/app/data_cleaner.py';
    protected string $crossRefScript = '/app/cross_reference.py';

    // =========================================================================
    // ENTRY POINT: clean a single uploaded file (no reference files)
    // =========================================================================

    public function cleanUploadedFile(string $pathOrStorage, array $options = []): array
    {
        $inputPath = $this->resolvePath($pathOrStorage);
        $userId    = Auth::id() ?? 'shared';
        $outputDir = $this->sharedDataPath . '/cleaned/' . $userId;
        $this->ensureDir($outputDir);

        return $this->cleanFile($inputPath, $outputDir, $options);
    }

    // =========================================================================
    // CLEAN SINGLE FILE — data_cleaner.py
    // =========================================================================

    public function cleanFile(string $inputPath, string $outputDir, array $options = []): array
    {
        $pythonInput  = $this->toDockerPath($inputPath);
        $basename     = pathinfo($inputPath, PATHINFO_FILENAME);
        $outputFile   = $outputDir . '/' . $basename . '_CLEANED.csv';
        $pythonOutput = $this->toDockerPath($outputFile);

        $command = $this->baseDockerCommand([
            'python3', '-u', $this->cleanerScript,
            $pythonInput,
            $pythonOutput,
        ]);

        Log::info('[DataCleaner] Running', ['input' => $pythonInput, 'output' => $pythonOutput]);

        $process = new Process($command);
        $process->setTimeout(3600);

        try {
            $process->mustRun();
            $result = $this->parseJsonOutput($process->getOutput());

            if (file_exists($outputFile)) {
                @chmod($outputFile, 0666);
                $result['cleaned_file_path'] = $outputFile;
            }

            Log::info('[DataCleaner] Done', $result);
            return $result;

        } catch (ProcessFailedException $e) {
            $err = $process->getErrorOutput() ?: $process->getOutput();
            Log::error('[DataCleaner] Failed', ['error' => $err]);
            throw new \Exception("Data cleaning failed: " . $err);
        }
    }

    // =========================================================================
    // FULL PIPELINE — correct order:
    //   Step 1: cross_reference.py  (merge + dedup + validate + enrich)
    //   Step 2: data_cleaner.py     (sanitize formats on merged dataset)
    // =========================================================================

    public function runFullPipeline(string $mainFile, array $referenceFiles = [], array $options = []): array
    {
        $userId      = Auth::id() ?? 'shared';
        $mergedDir   = $this->sharedDataPath . '/merged/'  . $userId;
        $cleanedDir  = $this->sharedDataPath . '/cleaned/' . $userId;
        $resultsDir  = $this->sharedDataPath . '/results/' . $userId;
        $this->ensureDir($mergedDir);
        $this->ensureDir($cleanedDir);
        $this->ensureDir($resultsDir);

        $basename = pathinfo($mainFile, PATHINFO_FILENAME);

        // ── STEP 1: cross_reference.py ───────────────────────────────────────
        // Merge all files, deduplicate on the full dataset, validate, enrich
        Log::info('[Pipeline] Step 1: cross_reference.py', [
            'main'  => $mainFile,
            'refs'  => $referenceFiles,
        ]);

        $crossRefResult = $this->runCrossReference(
            $mainFile, $referenceFiles, $mergedDir, $options
        );

        // The merged+validated file becomes the input for cleaning
        $mergedFile = $crossRefResult['output_csv']
            ?? ($mergedDir . '/' . $basename . '_CLEANED_ENRICHED.csv');

        if (!file_exists($mergedFile)) {
            // Fallback: cross_reference may have used a different naming pattern
            $candidates = glob($mergedDir . '/*ENRICHED*.csv') ?: glob($mergedDir . '/*.csv');
            if (!empty($candidates)) {
                $mergedFile = $candidates[0];
            } else {
                // No reference files — use main file directly
                $mergedFile = $mainFile;
            }
        }

        // ── STEP 2: data_cleaner.py ──────────────────────────────────────────
        // Sanitize dates, prices, negatives on the merged+deduped dataset
        Log::info('[Pipeline] Step 2: data_cleaner.py', ['input' => $mergedFile]);

        $cleanResult = $this->cleanFile($mergedFile, $cleanedDir, $options);

        // ── Build final result ────────────────────────────────────────────────
        $cleanedFile = $cleanedDir . '/' . pathinfo($mergedFile, PATHINFO_FILENAME) . '_CLEANED.csv';

        $result = [
            'status'               => 'success',
            'step1_cross_ref'      => $crossRefResult,
            'step2_cleaned'        => $cleanResult,
            'merged_file'          => $mergedFile,
            'cleaned_file_path'    => file_exists($cleanedFile) ? $cleanedFile : ($cleanResult['output_file'] ?? null),
            'report_file'          => $crossRefResult['output_report'] ?? null,
            'initial_rows'         => $crossRefResult['final_rows']    ?? 0,
            'final_rows'           => $cleanResult['rows']             ?? 0,
            'final_cols'           => $cleanResult['columns']          ?? 0,
            'null_remaining'       => $cleanResult['null_remaining']   ?? 0,
            'dedup_after_merge'    => $crossRefResult['rapport']['dedup_after_merge'] ?? 0,
        ];

        // Permissions
        foreach (['cleaned_file_path', 'merged_file', 'report_file'] as $key) {
            if (!empty($result[$key]) && file_exists($result[$key])) {
                @chmod($result[$key], 0666);
            }
        }

        Log::info('[Pipeline] Full pipeline complete', $result);
        return $result;
    }

    // =========================================================================
    // CROSS-REFERENCE ONLY — cross_reference.py
    // =========================================================================

    public function crossReferenceOnly(string $cleanedFile, array $referenceFiles, array $options = []): array
    {
        $userId     = Auth::id() ?? 'shared';
        $resultsDir = $this->sharedDataPath . '/results/' . $userId;
        $this->ensureDir($resultsDir);

        return $this->runCrossReference($cleanedFile, $referenceFiles, $resultsDir, $options);
    }

    // =========================================================================
    // BATCH
    // =========================================================================

    public function cleanBatch(array $files, array $options = []): array
    {
        $results = [];
        foreach ($files as $file) {
            try {
                $results[] = $this->cleanUploadedFile($file, $options);
            } catch (\Exception $e) {
                $results[] = [
                    'status'     => 'error',
                    'input_file' => $file,
                    'message'    => $e->getMessage(),
                ];
            }
        }
        return $results;
    }

    // =========================================================================
    // INTERNAL: run cross_reference.py
    // =========================================================================

    protected function runCrossReference(
        string $mainFile,
        array  $referenceFiles,
        string $outputDir,
        array  $options = []
    ): array {
        $pythonMain = $this->toDockerPath($mainFile);
        $pythonRefs = array_map(fn($f) => $this->toDockerPath($f), $referenceFiles);
        $pythonOut  = $this->toDockerPath($outputDir);

        $command = $this->baseDockerCommand(array_merge(
            ['python3', '-u', $this->crossRefScript],
            [$pythonMain],
            $pythonRefs,
            ['--output', $pythonOut]
        ));

        if (!empty($options['rules_file'])) {
            $command[] = '--rules';
            $command[] = $this->toDockerPath($options['rules_file']);
        }
        if (!empty($options['no_llm_enricher'])) {
            $command[] = '--no-llm-enricher';
        }

        Log::info('[CrossRef] Running', ['main' => $pythonMain, 'refs' => $pythonRefs]);

        $process = new Process($command);
        $process->setTimeout(3600);

        try {
            $process->mustRun();
            $result = $this->parseJsonOutput($process->getOutput());
            Log::info('[CrossRef] Done', $result);
            return $result;
        } catch (ProcessFailedException $e) {
            $err = $process->getErrorOutput() ?: $process->getOutput();
            Log::error('[CrossRef] Failed', ['error' => $err]);
            throw new \Exception("Cross-reference failed: " . $err);
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Build the base docker exec command with env vars injected.
     */
    protected function baseDockerCommand(array $pythonCommand): array
    {
        return array_merge([
            'docker', 'exec',
            '-e', 'GEMINI_API_KEY=' . (env('GEMINI_API_KEY') ?? ''),
            '-e', 'PYTHONUNBUFFERED=1',
            $this->containerName,
        ], $pythonCommand);
    }

    /**
     * Resolve a storage-relative or absolute path to a real filesystem path.
     */
    protected function resolvePath(string $pathOrStorage): string
    {
        if (file_exists($pathOrStorage)) {
            return $pathOrStorage;
        }
        $disk = Storage::disk('local');
        if (!$disk->exists($pathOrStorage)) {
            throw new \Exception("File not found: {$pathOrStorage}");
        }
        return $disk->path($pathOrStorage);
    }

    /**
     * Convert a host path to its Docker container equivalent (/shared_data/...).
     */
    protected function toDockerPath(string $path): string
    {
        if (str_starts_with($path, '/shared_data')) {
            return $path;
        }
        return str_replace(
            [storage_path('app/shared_data'), '/app/storage/app/shared_data'],
            $this->sharedDataPath,
            $path
        );
    }

    /**
     * Parse JSON from Python stdout — searches from end of output for robustness.
     */
    protected function parseJsonOutput(string $stdout): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $stdout)));

        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Regex fallback for multi-line JSON
        if (preg_match('/(\{(?:[^{}]|(?R))*\})/s', $stdout, $m)) {
            $decoded = json_decode($m[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Text fallback
        if (preg_match('/(success|Sanitize done|PIPELINE TERMINE)/i', $stdout)) {
            return ['status' => 'success', 'message' => 'Processing completed', 'rows' => 0, 'columns' => 0];
        }

        throw new \Exception("Could not parse Python output: " . substr($stdout, 0, 500));
    }

    protected function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    public function getSupportedFormats(): array
    {
        return ['xlsx', 'xls', 'csv', 'txt', 'json', 'xml'];
    }

    public function isFormatSupported(string $filename): bool
    {
        return in_array(
            strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
            $this->getSupportedFormats(),
            true
        );
    }
}