<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VisualizationController extends Controller
{
    public function index()
    {
        return view('visualise');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'dataset' => 'required|file|mimes:csv,xlsx,xls|max:20480',
        ]);

        // 1) Upload opslaan (private/local disk)
        $file = $request->file('dataset');
        $filename = now()->format('Ymd_His_u') . "_" . $file->getClientOriginalName();
        $storedPath = $file->storeAs('uploads', $filename, 'local');

        $inputPath = Storage::disk('local')->path($storedPath);
        if (!file_exists($inputPath)) {
            return back()->with('error', "Upload not found: $inputPath");
        }

        // 2) Output folder (public disk) => storage/app/public/reports/<uuid>
        $reportId = (string) Str::uuid();
        $outRel = "reports/$reportId";
        Storage::disk('public')->makeDirectory($outRel);
        $outDirFull = Storage::disk('public')->path($outRel);

        // 3) Python script runnen
        $python = "python3";
        $script = base_path("python_scripts/visualize.py");

        $process = new Process([$python, $script, $inputPath, $outDirFull]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            return back()->with('error',
                "Python error:\n" .
                $process->getErrorOutput() . "\n" .
                $process->getOutput()
            );
        }

        return redirect()->route('visualise.show', ['id' => $reportId]);
    }

    public function show($id)
    {
        $summaryPath = Storage::disk('public')->path("reports/$id/summary.json");
        if (!file_exists($summaryPath)) {
            abort(404, "Report not found");
        }

        $summary = json_decode(file_get_contents($summaryPath), true);

        return view('report', [
            'id' => $id,
            'summary' => $summary,
        ]);
    }
}
