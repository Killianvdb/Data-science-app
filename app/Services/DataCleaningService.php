<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

/**
 * DataCleaningService v5.0
 * ========================
 * Pipeline 4 étapes :
 *   1. data_cleaner.py  sur chaque fichier individuellement
 *   2. cross_reference.py  sur les fichiers nettoyés (merge + validate + enrich)
 *   3. data_cleaner.py  sur le fichier fusionné (re-nettoyage final)
 *
 * Pourquoi 3 passes ?
 *   - Pass 1 : nettoie les erreurs internes à chaque fichier
 *   - Cross-ref : détecte les doublons inter-fichiers, enrichit les NULLs
 *   - Pass 2 : re-nettoie ce que le merge a pu introduire (NaN, incohérences)
 */
class DataCleaningService
{
    protected string $containerName  = 'datasci-python';
    protected string $sharedDataPath = '/shared_data';
    protected string $cleanerScript  = '/app/data_cleaner.py';
    protected string $crossRefScript = '/app/cross_reference.py';

    // =========================================================================
    // ENTRY POINT: clean a single uploaded file (no cross-ref needed)
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

        Log::info('[DataCleaner] Running', ['input' => $pythonInput]);

        $process = new Process($command);
        $process->setTimeout(3600);

        try {
            $process->mustRun();
            $result = $this->parseJsonOutput($process->getOutput());

            if (file_exists($outputFile)) {
                @chmod($outputFile, 0666);
                $result['cleaned_file_path'] = $outputFile;
            }

            return $result;

        } catch (ProcessFailedException $e) {
            $err = $process->getErrorOutput() ?: $process->getOutput();
            Log::error('[DataCleaner] Failed', ['error' => $err]);
            throw new \Exception("Data cleaning failed: " . $err);
        }
    }

    // =========================================================================
    // FULL PIPELINE — 3 passes
    //
    //   Pass 1 : clean each file individually
    //   Pass 2 : cross-reference (merge + dedup inter-files + validate + enrich)
    //   Pass 3 : re-clean the merged file
    // =========================================================================

    public function runFullPipeline(string $mainFile, array $referenceFiles = [], array $options = []): array
    {
        $userId    = Auth::id() ?? 'shared';
        $pass1Dir  = $this->sharedDataPath . '/cleaned/'  . $userId;   // après pass 1
        $crossDir  = $this->sharedDataPath . '/merged/'   . $userId;   // après cross-ref
        $finalDir  = $this->sharedDataPath . '/results/'  . $userId;   // après pass 2
        foreach ([$pass1Dir, $crossDir, $finalDir] as $dir) {
            $this->ensureDir($dir);
        }

        // ── PASS 1 : clean each file individually ────────────────────────────
        Log::info('[Pipeline] Pass 1 — clean individual files');

        $mainPass1 = $this->cleanFile($mainFile, $pass1Dir, $options);
        $mainPass1Path = $mainPass1['cleaned_file_path']
            ?? ($pass1Dir . '/' . pathinfo($mainFile, PATHINFO_FILENAME) . '_CLEANED.csv');

        $refPass1Paths = [];
        foreach ($referenceFiles as $refFile) {
            $refCleaned      = $this->cleanFile($refFile, $pass1Dir, $options);
            $refPass1Paths[] = $refCleaned['cleaned_file_path']
                ?? ($pass1Dir . '/' . pathinfo($refFile, PATHINFO_FILENAME) . '_CLEANED.csv');
        }

        // ── PASS 2 : cross-reference on cleaned files ────────────────────────
        Log::info('[Pipeline] Pass 2 — cross-reference', [
            'main' => $mainPass1Path,
            'refs' => $refPass1Paths,
        ]);

        $crossResult    = $this->runCrossReference($mainPass1Path, $refPass1Paths, $crossDir, $options);
        $mergedFilePath = $crossResult['output_csv'] ?? null;

        // ── PASS 3 : re-clean the merged file ───────────────────────────────
        // Seulement si un fichier mergé a été produit
        $finalResult = null;
        $finalPath   = null;

        if ($mergedFilePath && file_exists($mergedFilePath)) {
            Log::info('[Pipeline] Pass 3 — re-clean merged file', ['file' => $mergedFilePath]);
            $finalResult = $this->cleanFile($mergedFilePath, $finalDir, $options);
            $finalPath   = $finalResult['cleaned_file_path'] ?? null;
        } else {
            // Pas de merge (fichier unique) — le résultat de pass 1 est le final
            Log::info('[Pipeline] Pass 3 — skipped (no merged file), using pass 1 output');
            $finalPath = $mainPass1Path;
        }

        // ── Build result ─────────────────────────────────────────────────────
        $result = [
            'status'            => 'success',
            'pass1_cleaned'     => $mainPass1,
            'pass2_cross_ref'   => $crossResult,
            'pass3_final'       => $finalResult,
            'cleaned_file_path' => $mainPass1Path,    // fichier nettoyé seul (pass 1)
            'enriched_file'     => $mergedFilePath,   // fichier mergé (pass 2)
            'final_file'        => $finalPath,        // fichier final re-nettoyé (pass 3)
            'report_file'       => $crossResult['output_report'] ?? null,
            'final_rows'        => $finalResult['rows']            ?? ($crossResult['final_rows']    ?? 0),
            'final_cols'        => $finalResult['columns']         ?? ($crossResult['final_cols']    ?? 0),
            'null_remaining'    => $finalResult['null_remaining']  ?? ($crossResult['null_remaining'] ?? 0),
        ];

        foreach (['cleaned_file_path', 'enriched_file', 'final_file', 'report_file'] as $key) {
            if (!empty($result[$key]) && file_exists($result[$key])) {
                @chmod($result[$key], 0666);
            }
        }

        Log::info('[Pipeline] Full pipeline complete — 3 passes done', $result);
        return $result;
    }

    // =========================================================================
    // CROSS-REFERENCE ONLY
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

        $process = new Process($command);
        $process->setTimeout(3600);

        try {
            $process->mustRun();
            return $this->parseJsonOutput($process->getOutput());
        } catch (ProcessFailedException $e) {
            $err = $process->getErrorOutput() ?: $process->getOutput();
            Log::error('[CrossRef] Failed', ['error' => $err]);
            throw new \Exception("Cross-reference failed: " . $err);
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    protected function baseDockerCommand(array $pythonCommand): array
    {
        return array_merge([
            'docker', 'exec',
            '-e', 'GEMINI_API_KEY=' . (env('GEMINI_API_KEY') ?? ''),
            '-e', 'PYTHONUNBUFFERED=1',
            $this->containerName,
        ], $pythonCommand);
    }

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

    protected function parseJsonOutput(string $stdout): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $stdout)));

        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/(\{(?:[^{}]|(?R))*\})/s', $stdout, $m)) {
            $decoded = json_decode($m[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

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