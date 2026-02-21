<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VisualizationController extends Controller
{
    /**
     * Show the upload form
     */
    public function index()
    {
        return view('visualise');
    }

    /**
     * Handle the file upload and trigger Python
     */
    public function generate(Request $request)
    {
        $request->validate([
            'dataset' => 'required|file|mimes:csv,xlsx,xls',
            'charts' => 'nullable|array',
            'charts.*' => 'in:missing_values,dtypes,histogram,pie,line',
            'chart_columns' => 'nullable|array',
        ]);

        // unique report id
        $reportId = (string) Str::uuid();

        // ===== 1) Save uploaded file to local/private storage (shared with python container) =====
        $file = $request->file('dataset');
        $ext = '.' . strtolower($file->getClientOriginalExtension());

        $srcRelDir = "report_sources/$reportId";
        Storage::disk('local')->makeDirectory($srcRelDir);

        $srcRelPath = $file->storeAs($srcRelDir, "source$ext", 'local');
        $inputPath = Storage::disk('local')->path($srcRelPath);

        if (!file_exists($inputPath)) {
            return back()->with('error', "Upload not found: " . $inputPath);
        }

        // make readable for python container
        @chmod($inputPath, 0666);

        // ===== 2) Public output folder for report images/json =====
        $outRel = "reports/$reportId";
        Storage::disk('public')->makeDirectory($outRel);
        $outDirFull = Storage::disk('public')->path($outRel);

        // make writable for python container
        @chmod($outDirFull, 0777);

        // ===== 3) Options passed to python =====
        $options = [
            'charts' => $request->input('charts', ['missing_values', 'dtypes']),
            'chart_columns' => $request->input('chart_columns', []),
        ];

        // ===== 4) Map Laravel paths -> python container paths =====
        // Laravel local(private): storage/app/private/...  -> python: /app/python_shared_data/...
        $pythonInput = str_replace(storage_path('app/private'), '/app/python_shared_data', $inputPath);

        // Laravel public: storage/app/public/... -> python: /app/python_shared_data/public/...
        $pythonOutput = str_replace(storage_path('app/public'), '/app/python_shared_data/public', $outDirFull);

        // ===== 5) Execute python inside datasci-python container =====
        $command = [
            'docker', 'exec', 'datasci-python',
            'python', '/app/python_scripts/visualize.py',
            $pythonInput,
            $pythonOutput,
            json_encode($options),
        ];

        $process = new Process($command);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            return back()->with('error',
                "Python processing failed:\n" .
                $process->getErrorOutput() . "\n" .
                $process->getOutput()
            );
        }

        return redirect()->route('visualise.show', ['id' => $reportId]);
    }

    /**
     * Re-run visualization for an existing report (new chart selections)
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'charts' => 'nullable|array',
            'charts.*' => 'in:missing_values,dtypes,histogram,pie,line',
            'chart_columns' => 'nullable|array',
        ]);

        // find the original uploaded source file (storage/app/private/report_sources/<id>/source.*)
        $srcRelDir = "report_sources/$id";
        $files = Storage::disk('local')->files($srcRelDir);

        if (empty($files)) {
            return back()->with('error', "Source file not found for report: $id");
        }

        $inputPath = Storage::disk('local')->path($files[0]);
        @chmod($inputPath, 0666);

        // output dir (public)
        $outRel = "reports/$id";
        Storage::disk('public')->makeDirectory($outRel);
        $outDirFull = Storage::disk('public')->path($outRel);
        @chmod($outDirFull, 0777);

        $options = [
            'charts' => $request->input('charts', ['missing_values', 'dtypes']),
            'chart_columns' => $request->input('chart_columns', []),
        ];

        // map paths for python container
        $pythonInput = str_replace(storage_path('app/private'), '/app/python_shared_data', $inputPath);
        $pythonOutput = str_replace(storage_path('app/public'), '/app/python_shared_data/public', $outDirFull);

        $command = [
            'docker', 'exec', 'datasci-python',
            'python', '/app/python_scripts/visualize.py',
            $pythonInput,
            $pythonOutput,
            json_encode($options),
        ];

        $process = new Process($command);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            return back()->with('error',
                "Python processing failed:\n" .
                $process->getErrorOutput() . "\n" .
                $process->getOutput()
            );
        }

        return redirect()->route('visualise.show', ['id' => $id]);
    }

    /**
     * Display the generated report
     */
    public function show(string $id)
    {
        $summaryPath = Storage::disk('public')->path("reports/$id/summary.json");

        if (!file_exists($summaryPath)) {
            abort(404, "Report not found (summary.json missing).");
        }

        $summary = json_decode(file_get_contents($summaryPath), true) ?? [];

        return view('report', [
            'id' => $id,
            'summary' => $summary,
        ]);
    }
}
