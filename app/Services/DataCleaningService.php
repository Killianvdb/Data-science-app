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
        // The path INSIDE the Python container
        $this->scriptPath = '/app/python_scripts/data_cleaner.py';
        $this->containerName = 'datasci-python';

        Log::info('DataCleaningService initialized for Docker environment');
    }

    /**
     * Clean a single file (Docker version)
     */
    public function cleanFile(string $inputPath, string $outputPath, array $options = []): array
    {
        // 1. Convert Laravel absolute paths to the paths the Python container sees.
        // Laravel: /app/storage/app/private/data_mission/file.csv
        // Python:  /app/python_shared_data/data_mission/file.csv
        $pythonInput = str_replace(storage_path('app/private'), '/app/python_shared_data', $inputPath);
        $pythonOutput = str_replace(storage_path('app/private'), '/app/python_shared_data', $outputPath);

        // 2. Build the Docker command
        $command = [
           'docker', 'exec', '-u', 'root', $this->containerName, // Added -u root
            'python', $this->scriptPath,
            $pythonInput,
            $pythonOutput
        ];

        if (!empty($options)) {
            $command[] = json_encode($options, JSON_UNESCAPED_UNICODE);
        }

        Log::info('Running python cleaner via Docker', ['cmd' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(3600);

        try {
            $process->mustRun();

            $stdout = trim($process->getOutput());
            
            // Extract JSON from potential logs
            $result = json_decode($stdout, true);
            if (!$result) {
                $json = $this->extractJson($stdout);
                $result = $json ? json_decode($json, true) : null;
            }

            if (!$result) {
                throw new \Exception("Failed to parse Python output. STDOUT: {$stdout}");
            }

            return $result;

        } catch (ProcessFailedException $e) {
            $error = trim($process->getErrorOutput()) ?: $e->getMessage();
            Log::error('Data cleaning failed', ['error' => $error]);
            throw new \Exception("Data cleaning failed: " . $error);
        }
    }

    private function extractJson(string $text): ?string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        return substr($text, $start, $end - $start + 1);
    }

    /**
     * Clean uploaded file
     */
    public function cleanUploadedFile(string $pathOrStorage, array $options = []): array
    {
        $inputPath = null;
        $baseNameForOutput = null;

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

        $userId = Auth::id() ?? 'shared';

        // Set output to our shared volume folder
       $outputDir = storage_path('app/private/cleaned/' . $userId);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputFilename = $baseNameForOutput . '_CLEANED.csv';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $outputFilename;

        $result = $this->cleanFile($inputPath, $outputPath, $options);

        // Path for the controller to use
        $result['cleaned_file_path'] = 'python_shared_data/cleaned/' . $userId . '/' . $outputFilename;

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