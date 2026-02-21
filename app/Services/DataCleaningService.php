<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DataCleaningService
{
    protected string $containerName;
    protected string $workflowPath;
    protected string $sharedDataPath;

    public function __construct()
    {
        $this->containerName = 'datasci-python';
        $this->workflowPath  = '/app/workflow.py';
        $this->sharedDataPath = '/shared_data';
    }

    // =========================================================================
    // CLEAN SINGLE FILE
    // =========================================================================

    /**
     * Clean a single file using data_cleaner.py directly (plus fiable que workflow clean).
     * Bug fix : on passe un chemin de FICHIER en sortie, pas un dossier.
     */
    public function cleanFile(string $inputPath, string $outputDir, array $options = []): array
    {
        $pythonInput = $this->toDockerPath($inputPath);

        // ✅ FIX 1 : construire le chemin du fichier de sortie, pas juste le dossier
        $basename    = pathinfo($inputPath, PATHINFO_FILENAME);
        $outputFile  = $outputDir . '/' . $basename . '_CLEANED.csv';
        $pythonOutput = $this->toDockerPath($outputFile);

        $command = [
            'docker', 'exec',
            '-e', 'GEMINI_API_KEY=' . (env('GEMINI_API_KEY') ?? ''),  // ✅ FIX 2 : passer la clé API
            '-e', 'PYTHONUNBUFFERED=1',
            $this->containerName,
            'python3', '-u',
            '/app/data_cleaner.py',   // appel direct, plus sûr que via workflow
            $pythonInput,
            $pythonOutput,
        ];

        Log::info('Running data_cleaner.py', ['command' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(3600);

        try {
            $process->mustRun();

            $stdout = $process->getOutput();
            Log::info('data_cleaner.py stdout', ['stdout' => $stdout]);

            $result = $this->parseJsonOutput($stdout);

            // S'assurer que le fichier nettoyé existe
            if (file_exists($outputFile)) {
                @chmod($outputFile, 0666);
                $result['cleaned_file_path'] = $outputFile;
            }

            return $result;

        } catch (ProcessFailedException $e) {
            $stderr = $process->getErrorOutput();
            $stdout = $process->getOutput();
            Log::error('data_cleaner.py failed', [
                'stderr' => $stderr,
                'stdout' => $stdout,
            ]);
            throw new \Exception("Data cleaning failed: " . ($stderr ?: $stdout));
        }
    }

    // =========================================================================
    // CLEAN UPLOADED FILE (entry point depuis le controller)
    // =========================================================================

    public function cleanUploadedFile(string $pathOrStorage, array $options = []): array
    {
        // Résoudre le chemin réel
        if (file_exists($pathOrStorage)) {
            $inputPath = $pathOrStorage;
        } else {
            $disk = Storage::disk('local');
            if (!$disk->exists($pathOrStorage)) {
                throw new \Exception("File not found: {$pathOrStorage}");
            }
            $inputPath = $disk->path($pathOrStorage);
        }

        $userId = Auth::id() ?? 'shared';

        // Dossier de sortie
        $outputDir = '/shared_data/cleaned/' . $userId;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $result = $this->cleanFile($inputPath, $outputDir, $options);

        // Construire l'URL de téléchargement
        $basename    = pathinfo($inputPath, PATHINFO_FILENAME);
        $cleanedFile = $basename . '_CLEANED.csv';
        $cleanedPath = $outputDir . '/' . $cleanedFile;

        if (file_exists($cleanedPath)) {
            @chmod($cleanedPath, 0666);
            $result['cleaned_file_path'] = $cleanedPath;
        }

        return $result;
    }

    // =========================================================================
    // FULL PIPELINE
    // =========================================================================

    public function runFullPipeline(string $mainFile, array $referenceFiles = [], array $options = []): array
    {
        $pythonMainFile       = $this->toDockerPath($mainFile);
        $pythonReferenceFiles = array_map(fn($f) => $this->toDockerPath($f), $referenceFiles);

        $userId      = Auth::id() ?? 'shared';
        $cleanedDir  = $this->sharedDataPath . '/cleaned/' . $userId;
        $resultsDir  = $this->sharedDataPath . '/results/' . $userId;

        $command = [
            'docker', 'exec',
            '-e', 'GEMINI_API_KEY=' . (env('GEMINI_API_KEY') ?? ''),  // ✅ FIX 2
            '-e', 'PYTHONUNBUFFERED=1',
            $this->containerName,
            'python3', $this->workflowPath,
            'full',
            $pythonMainFile,
            ...$pythonReferenceFiles,
            '--clean-dir',    $cleanedDir,
            '--analysis-dir', $resultsDir,
        ];

        if (!empty($options['rules_file'])) {
            $command[] = '--rules';
            $command[] = $this->toDockerPath($options['rules_file']);
        }

        if (!empty($options['no_llm_enricher'])) {
            $command[] = '--no-llm-enricher';
        }

        Log::info('Running full pipeline', ['cmd' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(7200);

        try {
            $process->mustRun();

            $stdout = $process->getOutput();
            $result = $this->parseJsonOutput($stdout);

            // Chemins des fichiers produits
            $basename = pathinfo($mainFile, PATHINFO_FILENAME);
            $result['cleaned_file_path'] = $cleanedDir  . '/' . $basename . '_CLEANED.csv';
            $result['enriched_file']     = $resultsDir  . '/' . $basename . '_CLEANED_ENRICHED.csv';
            $result['report_file']       = $resultsDir  . '/' . $basename . '_CLEANED_REPORT.json';

            // Permissions
            foreach (['cleaned_file_path', 'enriched_file', 'report_file'] as $key) {
                if (!empty($result[$key]) && file_exists($result[$key])) {
                    @chmod($result[$key], 0666);
                }
            }

            Log::info('Full pipeline completed', $result);
            return $result;

        } catch (ProcessFailedException $e) {
            $stderr = $process->getErrorOutput();
            Log::error('Full pipeline failed', [
                'stderr' => $stderr,
                'stdout' => $process->getOutput(),
            ]);
            throw new \Exception("Full pipeline failed: " . ($stderr ?: $process->getOutput()));
        }
    }

    // =========================================================================
    // CROSS-REFERENCE ONLY
    // =========================================================================

    public function crossReferenceOnly(string $cleanedFile, array $referenceFiles, array $options = []): array
    {
        $pythonMainFile       = $this->toDockerPath($cleanedFile);
        $pythonReferenceFiles = array_map(fn($f) => $this->toDockerPath($f), $referenceFiles);

        $userId     = Auth::id() ?? 'shared';
        $resultsDir = $this->sharedDataPath . '/results/' . $userId;

        $command = [
            'docker', 'exec',
            '-e', 'GEMINI_API_KEY=' . (env('GEMINI_API_KEY') ?? ''),  // ✅ FIX 2
            '-e', 'PYTHONUNBUFFERED=1',
            $this->containerName,
            'python3', $this->workflowPath,
            'cross-ref',
            $pythonMainFile,
            ...$pythonReferenceFiles,
            '--output', $resultsDir,
        ];

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
            throw new \Exception("Cross-reference failed: " . $process->getErrorOutput());
        }
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
    // HELPERS
    // =========================================================================

    /**
     * ✅ FIX 3 : Parser robuste — cherche le JSON depuis la fin du stdout.
     * workflow.py et data_cleaner.py écrivent leurs logs sur stderr,
     * et le JSON de résultat sur stdout (dernière ligne).
     */
    private function parseJsonOutput(string $stdout): array
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $stdout)),
            fn($l) => !empty($l)
        );

        // Parcourir depuis la fin → trouver le premier JSON valide
        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                Log::info('Parsed JSON output', $decoded);
                return $decoded;
            }
        }

        // Fallback regex : bloc JSON le plus complet
        if (preg_match('/(\{(?:[^{}]|(?R))*\})/s', $stdout, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Fallback texte : si le process a quand même réussi
        if (
            str_contains($stdout, 'Sanitize done') ||
            str_contains($stdout, 'DONE') ||
            str_contains($stdout, 'TERMINATED') ||
            str_contains($stdout, 'success')
        ) {
            return [
                'status'  => 'success',
                'message' => 'Processing completed (text output parsed)',
                'rows'    => 0,
                'columns' => 0,
            ];
        }

        throw new \Exception(
            "Could not parse Python output. STDOUT: " . substr($stdout, 0, 500)
        );
    }

    /**
     * Convertit un chemin Laravel/host en chemin Docker (/shared_data/...)
     */
    private function toDockerPath(string $laravelPath): string
    {
        if (str_starts_with($laravelPath, '/shared_data')) {
            return $laravelPath;
        }

        return str_replace(
            [
                storage_path('app/shared_data'),
                '/app/storage/app/shared_data',
            ],
            $this->sharedDataPath,
            $laravelPath
        );
    }

    public function getSupportedFormats(): array
    {
        return ['xlsx', 'xls', 'csv', 'txt', 'json', 'xml'];
    }

    public function isFormatSupported(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedFormats(), true);
    }
}