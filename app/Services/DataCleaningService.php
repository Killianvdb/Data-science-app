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
        // Auto-detect Python path or use config
        //$this->pythonPath = config('services.python.path', 'python3');
        $this->pythonPath = base_path('venv/Scripts/python.exe');
        $this->scriptPath = base_path('python_scripts/data_cleaner.py');
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

        //temporary
        Log::info('Python used for cleaning', ['python' => $this->pythonPath]);

        // Add options as JSON if provided
        if (!empty($options)) {
            $command[] = json_encode($options, JSON_UNESCAPED_UNICODE);
        }

        Log::info('Running python cleaner via Docker', ['cmd' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout for large files

        try {
            $process->mustRun();

            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (!$result) {
                $json = $this->extractJson($stdout);
                $result = $json ? json_decode($json, true) : null;
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

    private function extractJson(string $text): ?string
    {
        // Use Storage facade to get the real path
        $disk = Storage::disk('local');

        // Check if file exists using Storage
        if (!$disk->exists($storagePath)) {
            throw new \Exception("File not found in storage: {$storagePath}");
        }

        // Get the actual filesystem path
        $inputPath = $disk->path($storagePath);

        Log::info('Processing file', [
            'storage_path' => $storagePath,
            'real_path' => $inputPath,
            'exists' => file_exists($inputPath)
        ]);

        // Generate output filename
        $pathInfo = pathinfo($storagePath);
        $outputFilename = $pathInfo['filename'] . '_CLEANED.csv';

        // Use storage_path for output
        $outputDir = storage_path('app/cleaned_output');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir . '/' . $outputFilename;

        $result = $this->cleanFile($inputPath, $outputPath, $options);

        // Add storage path to result
        $result['cleaned_file_path'] = 'cleaned_output/' . $outputFilename;

        return $result;
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
