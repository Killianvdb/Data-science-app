<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataCleaningService
{
    protected $pythonPath;
    protected $scriptPath;

    public function __construct()
    {
        // Auto-detect Python path or use config
        $this->pythonPath = config('services.python.path', 'python3');
        $this->scriptPath = base_path('python_scripts/data_cleaner.py');
    }

    /**
     * Clean a single file
     *
     * @param string $inputPath Full path to input file
     * @param string $outputPath Full path where cleaned CSV should be saved
     * @param array $options Optional cleaning parameters
     * @return array Result with status, message, and metadata
     */
    public function cleanFile($inputPath, $outputPath, array $options = [])
    {
        // Validate input file exists
        if (!file_exists($inputPath)) {
            throw new \Exception("Input file not found: {$inputPath}");
        }

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Build command
        $command = [
            $this->pythonPath,
            $this->scriptPath,
            $inputPath,
            $outputPath
        ];

        // Add options as JSON if provided
        if (!empty($options)) {
            $command[] = json_encode($options);
        }

        // Create and run process
        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout for large files
        
        try {
            $process->mustRun();
            
            $output = $process->getOutput();
            $result = json_decode($output, true);
            
            if (!$result) {
                throw new \Exception("Failed to parse Python script output");
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
     * Clean an uploaded file from Laravel storage
     *
     * @param string $storagePath Path in storage/app (e.g., 'uploads/file.xlsx')
     * @param array $options Optional cleaning parameters
     * @return array Result including the path to cleaned file
     */
    public function cleanUploadedFile($storagePath, array $options = [])
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
     * Process multiple files in batch
     *
     * @param array $files Array of storage paths
     * @param array $options Optional cleaning parameters
     * @return array Results for each file
     */
    public function cleanBatch(array $files, array $options = [])
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
     * Get list of supported file formats
     *
     * @return array
     */
    public function getSupportedFormats()
    {
        return ['xlsx', 'xls', 'csv', 'txt', 'json', 'xml'];
    }

    /**
     * Validate if file format is supported
     *
     * @param string $filename
     * @return bool
     */
    public function isFormatSupported($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedFormats());
    }
}