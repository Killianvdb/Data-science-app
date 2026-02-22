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
        $this->workflowPath = '/app/workflow.py';
        $this->sharedDataPath = '/shared_data'; // Path inside Docker containers
    }

    /**
     * Clean a single file using workflow.py (clean mode)
     */
    public function cleanFile(string $inputPath, string $outputPath, array $options = []): array
{
    $pythonInput = $this->toDockerPath($inputPath);
    $pythonOutput = $this->toDockerPath($outputPath);

    $command = [
        'docker', 'exec',
        $this->containerName,
        'python3', '-u',  // ← AJOUTE -u pour unbuffered output
        $this->workflowPath,
        'clean',
        $pythonInput,
        '--output', $pythonOutput
    ];

    Log::info('🐍 Running workflow.py clean', [
        'command' => implode(' ', $command)
    ]);

    $process = new Process($command);
    $process->setTimeout(3600);
    $process->setEnv(['PYTHONUNBUFFERED' => '1']);  // ← Unbuffered

    try {
        $process->mustRun();

        $stdout = $process->getOutput();
        $result = $this->parseWorkflowOutput($stdout);

        Log::info('✅ Data cleaning completed', $result);
        return $result;

    } catch (ProcessFailedException $exception) {
        $error = $process->getErrorOutput();
        Log::error('❌ Data cleaning failed', [
            'error' => $error,
            'stdout' => $process->getOutput(),
            'input' => $inputPath,
            'output' => $outputPath
        ]);

        throw new \Exception("Data cleaning failed: " . $error);
    }
}

    /**
     * Full pipeline with cross-reference
     * @param string $mainFile Main dataset to clean
     * @param array $referenceFiles Reference files for cross-reference
     * @param array $options Additional options (rules file, etc.)
     */
    public function runFullPipeline(string $mainFile, array $referenceFiles = [], array $options = []): array
    {
        $pythonMainFile = $this->toDockerPath($mainFile);
        $pythonReferenceFiles = array_map(fn($f) => $this->toDockerPath($f), $referenceFiles);

        $userId = Auth::id() ?? 'shared';
        $cleanedDir = $this->sharedDataPath . '/cleaned/' . $userId;
        $resultsDir = $this->sharedDataPath . '/results/' . $userId;

        // Build command for 'full' mode
        $command = [
            'docker', 'exec',
            $this->containerName,
            'python3', $this->workflowPath,
            'full',
            $pythonMainFile,
            ...$pythonReferenceFiles, // Spread reference files
            '--clean-dir', $cleanedDir,
            '--analysis-dir', $resultsDir
        ];

        // Add optional rules file
        if (!empty($options['rules_file'])) {
            $rulesPath = $this->toDockerPath($options['rules_file']);
            $command[] = '--rules';
            $command[] = $rulesPath;
        }

        // Add --no-llm-enricher flag if requested
        if (!empty($options['no_llm_enricher'])) {
            $command[] = '--no-llm-enricher';
        }

        Log::info('Running workflow.py full pipeline via Docker', ['cmd' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(7200); // 2 hours for full pipeline

        try {
            $process->mustRun();

            $stdout = $process->getOutput();
            $result = $this->parseWorkflowOutput($stdout);

            // Add paths to cleaned and enriched files
            $basename = pathinfo($mainFile, PATHINFO_FILENAME);
            $result['cleaned_file'] = 'cleaned/' . $userId . '/' . $basename . '_CLEANED.csv';
            $result['enriched_file'] = 'results/' . $userId . '/' . $basename . '_CLEANED_ENRICHED.csv';
            $result['report_file'] = 'results/' . $userId . '/' . $basename . '_CLEANED_REPORT.json';

            Log::info('Full pipeline completed', $result);
            return $result;

        } catch (ProcessFailedException $exception) {
            $error = $process->getErrorOutput();
            Log::error('Full pipeline failed', [
                'error' => $error,
                'stdout' => $process->getOutput()
            ]);

            throw new \Exception("Full pipeline failed: " . $error);
        }
    }

    /**
     * Cross-reference mode only (skip cleaning)
     */
    public function crossReferenceOnly(string $cleanedFile, array $referenceFiles, array $options = []): array
    {
        $pythonMainFile = $this->toDockerPath($cleanedFile);
        $pythonReferenceFiles = array_map(fn($f) => $this->toDockerPath($f), $referenceFiles);

        $userId = Auth::id() ?? 'shared';
        $resultsDir = $this->sharedDataPath . '/results/' . $userId;

        $command = [
            'docker', 'exec',
            $this->containerName,
            'python3', $this->workflowPath,
            'cross-ref',
            $pythonMainFile,
            ...$pythonReferenceFiles,
            '--output', $resultsDir
        ];

        if (!empty($options['rules_file'])) {
            $command[] = '--rules';
            $command[] = $this->toDockerPath($options['rules_file']);
        }

        if (!empty($options['no_llm_enricher'])) {
            $command[] = '--no-llm-enricher';
        }

        Log::info('Running cross-reference only', ['cmd' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(3600);

        try {
            $process->mustRun();
            return $this->parseWorkflowOutput($process->getOutput());
        } catch (ProcessFailedException $exception) {
            throw new \Exception("Cross-reference failed: " . $process->getErrorOutput());
        }
    }

    /**
     * Clean uploaded file (backward compatible)
     */
    public function cleanUploadedFile(string $pathOrStorage, array $options = []): array
{
    // Determine real file path
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

    // Copy file to shared_data for Docker access
    $sharedInputDir = '/shared_data/uploads/' . $userId;  // ← FIX
    if (!is_dir($sharedInputDir)) {
        mkdir($sharedInputDir, 0777, true);
    }

    $filename = basename($inputPath);
    $sharedInputPath = $sharedInputDir . '/' . $filename;
    copy($inputPath, $sharedInputPath);
    @chmod($sharedInputPath, 0666);

    // Output directory
    $outputDir = '/shared_data/cleaned/' . $userId;  // ← FIX
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    $result = $this->cleanFile($sharedInputPath, $outputDir, $options);

    // Find the cleaned file
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    $cleanedFile = $basename . '_CLEANED.csv';
    $cleanedPath = $outputDir . '/' . $cleanedFile;

    if (file_exists($cleanedPath)) {
        @chmod($cleanedPath, 0666);
        $result['cleaned_file_path'] = 'shared_data/cleaned/' . $userId . '/' . $cleanedFile;
    }

    return $result;
}

    /**
     * Batch cleaning
     */
    public function cleanBatch(array $files, array $options = []): array
    {
        $results = [];
        foreach ($files as $file) {
            try {
                $results[] = $this->cleanUploadedFile($file, $options);
            } catch (\Exception $e) {
                $results[] = [
                    'status' => 'error',
                    'input_file' => $file,
                    'message' => $e->getMessage()
                ];
            }
        }
        return $results;
    }

    /**
     * Convert Laravel storage path to Docker container path
     */
    private function toDockerPath(string $laravelPath): string
    {
        // Si déjà un chemin Docker, retourne tel quel
        if (str_starts_with($laravelPath, '/shared_data')) {
            return $laravelPath;
        }

        // Remplace tous les formats possibles par /shared_data
        return str_replace(
            [
                storage_path('app/shared_data'),  // /app/storage/app/shared_data
                '/app/storage/app/shared_data',   // Chemin absolu Docker
            ],
            $this->sharedDataPath,  // /shared_data
            $laravelPath
        );
    }

    /**
     * Parse workflow.py JSON output
     */
    private function parseWorkflowOutput(string $stdout): array
{
    // Le workflow affiche du texte + JSON à la fin
    // On cherche juste le bloc JSON

    // Méthode 1 : Cherche le dernier bloc JSON valide
    $lines = explode("\n", trim($stdout));

    // Parcours depuis la fin pour trouver le JSON
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;

        // Essaye de parser comme JSON
        $decoded = json_decode($line, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    // Méthode 2 : Extrait le JSON avec regex
    if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $stdout, $matches)) {
        $result = json_decode($matches[0], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }
    }

    // Méthode 3 : Fallback - crée un résultat manuel depuis le texte
    if (str_contains($stdout, '✅ TERMINATED') || str_contains($stdout, 'Succès')) {
        // Parse les infos du texte
        preg_match('/(\d+) lignes × (\d+) colonnes/', $stdout, $stats);
        preg_match('/Output\s+:\s+(.+)/', $stdout, $output);

        return [
            'status' => 'success',
            'rows' => isset($stats[1]) ? (int)$stats[1] : 0,
            'columns' => isset($stats[2]) ? (int)$stats[2] : 0,
            'output_dir' => isset($output[1]) ? trim($output[1]) : '',
            'message' => 'Cleaning completed successfully'
        ];
    }

    // Si rien ne marche, throw error
    throw new \Exception("Failed to parse workflow output. STDOUT: " . substr($stdout, 0, 1000));
}

    /**
     * Get supported file formats
     */
    public function getSupportedFormats(): array
    {
        return ['xlsx', 'xls', 'csv', 'txt', 'json', 'xml'];
    }

    /**
     * Check if format is supported
     */
    public function isFormatSupported(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedFormats(), true);
    }
}
