<?php
 
namespace App\Services;
 
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
 
class DataCleaningService
{
    protected string $pythonPath;
    protected string $scriptPath;
 
    public function __construct()
    {
        $this->pythonPath = $this->detectPythonPath();
        $this->scriptPath = base_path('python_scripts/data_cleaner.py');
 
        if (!file_exists($this->scriptPath)) {
            throw new \RuntimeException("Python script not found: {$this->scriptPath}");
        }
 
        Log::info('DataCleaningService initialized', [
            'python' => $this->pythonPath,
            'script' => $this->scriptPath,
        ]);
    }
 
    private function detectPythonPath(): string
    {
        // 1) Optional config override (als je ooit services.php gebruikt)
        $configured = config('services.python.path');
        if (is_string($configured) && $configured !== '' && file_exists($configured)) {
            return $configured;
        }
 
        // 2) Project venv candidates (Linux/macOS)
        $candidates = [
            base_path('venv/bin/python3'),
            base_path('venv/bin/python'),
            // 3) Windows venv candidate
            base_path('venv/Scripts/python.exe'),
        ];
 
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
 
        // 4) Fallback to PATH
        return 'python3';
    }
 
    /**
     * Clean a single file (absolute paths)
     */
    public function cleanFile(string $inputPath, string $outputPath, array $options = []): array
    {
        if (!file_exists($inputPath)) {
            throw new \Exception("Input file not found: {$inputPath}");
        }
 
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
 
        $command = [
            $this->pythonPath,
            $this->scriptPath,
            $inputPath,
            $outputPath,
        ];
 
        if (!empty($options)) {
            $command[] = json_encode($options, JSON_UNESCAPED_UNICODE);
        }
 
        Log::info('Running python cleaner', [
            'cmd' => $command,
        ]);
 
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->setWorkingDirectory(base_path());
 
        try {
            $process->mustRun();
 
            $stdout = trim($process->getOutput());
            $stderr = trim($process->getErrorOutput());
 
            // Soms print python extra logs -> probeer JSON te “extracten”
            $result = json_decode($stdout, true);
            if (!$result) {
                // probeer laatste JSON blok te vinden
                $json = $this->extractJson($stdout);
                $result = $json ? json_decode($json, true) : null;
            }
 
            if (!$result) {
                throw new \Exception("Failed to parse Python output. STDOUT: {$stdout} STDERR: {$stderr}");
            }
 
            Log::info('Data cleaning completed', $result);
 
            return $result;
 
        } catch (ProcessFailedException $e) {
            $error = trim($process->getErrorOutput());
            if ($error === '') {
                $error = $e->getMessage();
            }
 
            Log::error('Data cleaning failed', [
                'error' => $error,
                'input' => $inputPath,
                'output' => $outputPath,
            ]);
 
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
     *
     * Accepts either:
     * - storage path like: "data_mission/1/file.csv"
     * - absolute filesystem path like: "/home/karim/.../storage/app/....csv"
     */
    public function cleanUploadedFile(string $pathOrStorage, array $options = []): array
    {
        $inputPath = null;
        $baseNameForOutput = null;
 
        // A) absolute path
        if (file_exists($pathOrStorage)) {
            $inputPath = $pathOrStorage;
            $baseNameForOutput = pathinfo($inputPath, PATHINFO_FILENAME);
        } else {
            // B) storage path
            $disk = Storage::disk('local');
            if (!$disk->exists($pathOrStorage)) {
                throw new \Exception("File not found (storage or path): {$pathOrStorage}");
            }
            $inputPath = $disk->path($pathOrStorage);
            $baseNameForOutput = pathinfo($pathOrStorage, PATHINFO_FILENAME);
        }
 
        $userId = Auth::id() ?? 'shared';
 
        $outputDir = storage_path('app/cleaned_output/' . $userId);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
 
        $outputFilename = $baseNameForOutput . '_CLEANED.csv';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $outputFilename;
 
        Log::info('Cleaning file', [
            'input' => $inputPath,
            'output' => $outputPath,
            'user' => $userId,
        ]);
 
        $result = $this->cleanFile($inputPath, $outputPath, $options);
 
        // voor je controller download()
        $result['cleaned_file_path'] = 'cleaned_output/' . $userId . '/' . $outputFilename;
 
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