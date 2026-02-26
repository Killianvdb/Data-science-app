<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;


class VisualizationController extends Controller
{
    /**
     * Show the upload form
     */
    public function index()
    {
        return view('visualise', [
            'supportedFormats' => ['CSV', 'XLSX', 'XLS'],
        ]);
    }

    /**
     * Pre-load visualisation from an already-cleaned file on the shared volume.
     * Called via GET /visualise/from-cleaned/{filename}
     */
    public function fromCleaned(Request $request, string $filename)
    {
        $userId  = Auth::id();

        // Resolve the file on the shared volume (same path Laravel + Python see)
        $cleanedDir = "/shared_data/cleaned/{$userId}";
        $inputPath  = $cleanedDir . '/' . $filename;

        if (!file_exists($inputPath)) {
            return redirect()->route('visualise.index')
                ->with('error', "Cleaned file not found. Please process your file first.");
        }

        // Create output directory in Laravel public storage
        $reportId = (string) Str::uuid();
        $outRel   = "reports/{$reportId}";
        Storage::disk('public')->makeDirectory($outRel);
        $outDirFull = storage_path('app/public/' . $outRel);
        chmod($outDirFull, 0777);

        // Map output path for Python container
        // Laravel: storage/app/public/reports/... → Python: /app/python_shared_data/public/reports/...
        $pythonInput  = $inputPath; // same path, shared volume
        $pythonOutput = str_replace(
            storage_path('app/public'),
            '/app/python_shared_data/public',
            $outDirFull
        );

        // Run visualize.py
        $command = [
            'docker', 'exec', 'datasci-python',
            'python', '/app/python_scripts/visualize.py',
            $pythonInput,
            $pythonOutput,
        ];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
                Log::error('[fromCleaned] Failed', [
            'exit' => $process->getExitCode(),
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ]);
            return redirect()->route('visualise.index')
                ->with('error', 'Visualisation failed: ' . $process->getErrorOutput());
        }

        return redirect()->route('visualise.show', ['id' => $reportId]);
    }

    /**
     * Handle the file upload and trigger Python
     */
    public function generate(Request $request)
    {
        $request->validate([
            'dataset' => 'required|file|mimes:csv,xlsx,xls|max:102400',
        ]);

        // 1) Save to the SHARED private folder
        $file = $request->file('dataset');
        $filename = now()->format('Ymd_His_u') . "_" . $file->getClientOriginalName();
        
        $storedPath = $file->storeAs('uploads', $filename, 'local'); 
        $inputPath = storage_path('app/private/' . $storedPath);

        if (file_exists($inputPath)) {
            chmod($inputPath, 0666); 
        }

        // 2) Create Output folder in the SHARED public folder
        $reportId = (string) Str::uuid();
        $outRel = "reports/$reportId";
        Storage::disk('public')->makeDirectory($outRel);
        $outDirFull = storage_path('app/public/' . $outRel);
        chmod($outDirFull, 0777);

        // 3) MAP PATHS FOR PYTHON CONTAINER
        $pythonInput  = str_replace(storage_path('app/private'), '/app/python_shared_data', $inputPath);
        $pythonOutput = str_replace(storage_path('app/public'), '/app/python_shared_data/public', $outDirFull);

        // 4) Execute via Docker Bridge
        $command = [
            'docker', 'exec', 'datasci-python',
            'python', '/app/python_scripts/visualize.py', 
            $pythonInput,
            $pythonOutput
        ];

        $process = new Process($command);
        $process->setWorkingDirectory('/app/public'); 
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            return back()->with('error', "Python processing failed: " . $process->getErrorOutput());
        }

        return redirect()->route('visualise.show', ['id' => $reportId]);
    }

    /**
     * Display the generated report
     */
    public function show($id)
    {
        $summaryPath = storage_path("app/public/reports/$id/summary.json");

        if (!file_exists($summaryPath)) {
            abort(404, "The visualization report was not generated correctly.");
        }

        $summary = json_decode(file_get_contents($summaryPath), true);

        return view('report', [
            'id'      => $id,
            'summary' => $summary,
        ]);
    }
}