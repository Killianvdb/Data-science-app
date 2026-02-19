<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DataCleaningService
{
    protected string $scriptPath;
    protected string $containerName;

    public function __construct()
    {
        // 1. Initialize the property that was causing the crash
        $this->containerName = 'datasci-python';

        // 2. Path inside the PYTHON container to the cleaner script
        $this->scriptPath = '/app/python_scripts/data_cleaner.py';
    }

    /**
     * Clean a single file (Docker version)
     */
    public function cleanFile(string $inputPath, string $outputPath, array $options = []): array
    {
        // Convert Laravel absolute paths to the paths the Python container sees.
        $pythonInput = str_replace(storage_path('app/private'), '/app/python_shared_data', $inputPath);
        $pythonOutput = str_replace(storage_path('app/private'), '/app/python_shared_data', $outputPath);

        // Build the Docker command
        $command = [
            'docker', 'exec', '-u', 'root', 
            $this->containerName, 
            'python', $this->scriptPath,
            $pythonInput,
            $pythonOutput
        ];

        // Add options as JSON if provided
        if (!empty($options)) {
            $command[] = json_encode($options, JSON_UNESCAPED_UNICODE);
        }

        Log::info('Running python cleaner via Docker', ['cmd' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout

        try {
            $process->mustRun();

            $stdout = $process->getOutput();
            $result = json_decode($stdout, true);

            // If JSON decode fails, try to extract it from any stray text output
            if (!$result) {
                $jsonStr = $this->extractJsonFromText($stdout);
                $result = $jsonStr ? json_decode($jsonStr, true) : null;
            }

            if (!$result) {
                throw new \Exception("Failed to parse Python output. STDOUT: {$stdout}");
            }

            Log::info('Data cleaning completed', $result);
            return $result;

        } catch (ProcessFailedException $exception) {
            $error = $process->getErrorOutput();
            Log::error('Data cleaning failed', [
                'error' => $error,
                'input' => $inputPath,
                'output' => $outputPath
            ]);

            throw new \Exception("Data cleaning failed: " . $error);
        }
    }

    /**
     * Helper to find JSON block in a string if Python prints extra info
     */
    private function extractJsonFromText(string $text): ?string
    {
        preg_match('/\{.*\}/s', $text, $matches);
        return $matches[0] ?? null;
    }

    /**
     * Clean uploaded file
     */
    public function cleanUploadedFile(string $pathOrStorage, array $options = []): array
    {
        $inputPath = null;
        $baseNameForOutput = null;

        // Determine real file path
        if (file_exists($pathOrStorage)) {
            $inputPath = $pathOrStorage;
            $baseNameForOutput = pathinfo($inputPath, PATHINFO_FILENAME);
        } else {
            $disk = Storage::disk('local');
            if (!$disk->exists($pathOrStorage)) {
                throw new \Exception("File not found: {$pathOrStorage}");
            }
            $inputPath = $disk->path($pathOrStorage);
            $baseNameForOutput = pathinfo($pathOrStorage, PATHINFO_FILENAME);
        }

        // Set permissions for shared access
        @chmod($inputPath, 0666);

        $userId = Auth::id() ?? 'shared';

        // Set output to our shared volume folder
        $outputDir = storage_path('app/private/cleaned/' . $userId);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputFilename = $baseNameForOutput . '_CLEANED.csv';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $outputFilename;

        $result = $this->cleanFile($inputPath, $outputPath, $options);

        // Ensure the output file is readable by Laravel
        if (file_exists($outputPath)) {
            @chmod($outputPath, 0666);
        }

        // Path for the controller to use
        $result['cleaned_file_path'] = 'cleaned/' . $userId . '/' . $outputFilename;

        return $result;
    }

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
