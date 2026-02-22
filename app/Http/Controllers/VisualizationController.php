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

        $reportId = (string) Str::uuid();

        // 1) Input -> shared volume (zichtbaar voor python container)
        $file = $request->file('dataset');
        $ext  = strtolower($file->getClientOriginalExtension());

        $sharedInDir = "/shared_data/in/{$reportId}";
        if (!is_dir($sharedInDir)) {
            mkdir($sharedInDir, 0777, true);
        }

        $sharedInput = "{$sharedInDir}/source.{$ext}";
        $file->move($sharedInDir, "source.{$ext}");
        @chmod($sharedInput, 0666);

        if (!file_exists($sharedInput)) {
            return back()->with('error', "Upload not found in shared folder: {$sharedInput}");
        }

        // 2) Output -> shared volume
        $sharedOutDir = "/shared_data/out/{$reportId}";
        if (!is_dir($sharedOutDir)) {
            mkdir($sharedOutDir, 0777, true);
        }
        @chmod($sharedOutDir, 0777);

        // 3) Public output folder (Laravel toont images via storage:link)
        $outRel = "reports/{$reportId}";
        Storage::disk('public')->makeDirectory($outRel);
        $outPublicDir = Storage::disk('public')->path($outRel);
        @chmod($outPublicDir, 0777);

        // 4) Run python in datasci-python container
        // BELANGRIJK: script staat bij jou op /app/visualize.py
        $command = [
            '/usr/bin/docker', 'exec', 'datasci-python',
            'python', '/app/visualize.py',
            $sharedInput,
            $sharedOutDir,
        ];

        $process = new Process($command);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $exit = $process->getExitCode();
            $err  = trim($process->getErrorOutput());
            $out  = trim($process->getOutput());

            return back()->with('error',
                "Python processing failed (exit={$exit})\n" .
                "CMD:\n" . implode(' ', array_map('escapeshellarg', $command)) . "\n\n" .
                "STDERR:\n" . ($err !== '' ? $err : '[empty]') . "\n\n" .
                "STDOUT:\n" . ($out !== '' ? $out : '[empty]')
            );
        }

        // 5) Copy results (png + summary.json) -> storage/app/public/reports/{id}
        foreach (glob($sharedOutDir . '/*') as $p) {
            @copy($p, $outPublicDir . '/' . basename($p));
        }

        return redirect()->route('visualise.show', ['id' => $reportId]);
    }

    public function fromCleaned(Request $request)
    {
        // Signed URL check
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired visualisation link.');
        }

        $relPath = (string) $request->query('path', '');

        // Basic safety: alleen toestaan uit bepaalde folders
        if (!Str::startsWith($relPath, ['cleaned_output/', 'enriched_output/'])) {
            abort(403, 'Path not allowed.');
        }

        $inputPath = storage_path('app/' . $relPath);
        if (!file_exists($inputPath)) {
            abort(404, "Input file not found: $relPath");
        }

        $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        $reportId = (string) Str::uuid();

        // 1) Copy input -> shared volume (voor python container)
        $sharedInDir = "/shared_data/in/{$reportId}";
        if (!is_dir($sharedInDir)) mkdir($sharedInDir, 0777, true);

        $sharedInput = "{$sharedInDir}/source.{$ext}";
        copy($inputPath, $sharedInput);
        @chmod($sharedInput, 0666);

        // 2) Shared output
        $sharedOutDir = "/shared_data/out/{$reportId}";
        if (!is_dir($sharedOutDir)) mkdir($sharedOutDir, 0777, true);
        @chmod($sharedOutDir, 0777);

        // 3) Public output folder
        $outRel = "reports/{$reportId}";
        Storage::disk('public')->makeDirectory($outRel);
        $outPublicDir = Storage::disk('public')->path($outRel);
        @chmod($outPublicDir, 0777);

        // 4) Run python
        $process = new Process([
            'docker', 'exec', 'datasci-python',
            'python', '/app/visualize.py',
            $sharedInput,
            $sharedOutDir,
        ]);

        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $exit = $process->getExitCode();
            $err  = trim($process->getErrorOutput());
            $out  = trim($process->getOutput());

            return back()->with('error',
                "Python processing failed (exit={$exit})\n".
                "STDERR:\n".($err !== '' ? $err : '[empty]')."\n\n".
                "STDOUT:\n".($out !== '' ? $out : '[empty]')
            );
        }

        // 5) Copy results -> public
        foreach (glob($sharedOutDir . '/*') as $p) {
            @copy($p, $outPublicDir . '/' . basename($p));
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
