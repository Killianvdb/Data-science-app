<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dataset;
use App\Models\User;
use App\Services\DataCleaningService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;

class DatasetController extends Controller
{
    protected $cleaningService;

    public function __construct(DataCleaningService $cleaningService)
    {
        $this->cleaningService = $cleaningService;
    }

    /**
     * Show the upload form
     */
    public function index()
    {
        $supportedFormats = $this->cleaningService->getSupportedFormats();
        $datasets = Dataset::where('user_id', Auth::id())->get();
        $planSlug = Auth::user()->plan?->slug;

        return view('datasets.index', compact('supportedFormats', 'planSlug'));
    }

    /**
     * Handle file upload with optional cross-reference
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt,json,xml|max:20480',
            'reference_files.*' => 'nullable|file|mimes:xlsx,xls,csv|max:10240',
            'pipeline_mode' => 'nullable|string|in:clean_only,full_pipeline',
            'use_llm_enricher' => 'nullable|boolean',
            'rules_file' => 'nullable|file|mimes:json|max:1024',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $plan = $user->plan;

            if (!$plan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No subscription plan found for this user.'
                ], 403);
            }

            $file = $request->file('file');
            $maxTotalBytes = $plan->max_total_mb_per_transaction * 1024 * 1024;
            $isPro = $user->plan?->slug === 'pro';

            if ($file->getSize() > $maxTotalBytes) {
                return response()->json([
                    'status' => 'error',
                    'message' => $isPro 
                        ? 'This file exceeds the current Pro max size (20MB). Contact support if you need a higher limit.'
                        : 'File exceeds maximum size for your plan.'
                ], 403);
            }

            if (!$user->canUpload(1)) {
                return response()->json([
                    'status' => 'error',
                    'message' => $isPro 
                        ? 'You reached our fair-use security limit. Please contact support to increase it.'
                        : 'Monthly limit reached. Please upgrade your plan.'
                ], 403);
            }

            // Store main file in shared_data for Docker access
            $userId = Auth::id();
            $uploadDir = '/shared_data/uploads/' . $userId;
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $mainFileName = time() . '_' . $file->getClientOriginalName();
            $mainFilePath = $uploadDir . '/' . $mainFileName;
            $file->move($uploadDir, $mainFileName);
            @chmod($mainFilePath, 0666);

            // Handle reference files if provided
            $referenceFiles = [];
            if ($request->hasFile('reference_files')) {
                foreach ($request->file('reference_files') as $refFile) {
                    $refFileName = time() . '_ref_' . $refFile->getClientOriginalName();
                    $refFilePath = $uploadDir . '/' . $refFileName;
                    $refFile->move($uploadDir, $refFileName);
                    @chmod($refFilePath, 0666);
                    $referenceFiles[] = $refFilePath;
                }
            }

            // Options
            $options = [
                'no_llm_enricher' => !$request->boolean('use_llm_enricher', true)
            ];

            // Handle rules file if provided
            if ($request->hasFile('rules_file')) {
                $rulesFile = $request->file('rules_file');
                $rulesFileName = 'rules_' . time() . '.json';
                $rulesPath = $uploadDir . '/' . $rulesFileName;
                $rulesFile->move($uploadDir, $rulesFileName);
                @chmod($rulesPath, 0666);
                $options['rules_file'] = $rulesPath;
            }

            // Determine pipeline mode
            $pipelineMode = $request->input('pipeline_mode', 'full_pipeline');

            if ($pipelineMode === 'clean_only' || empty($referenceFiles)) {
                // Clean only mode
                $result = $this->cleaningService->cleanUploadedFile($mainFilePath, $options);
            } else {
                // Full pipeline with cross-reference
                $result = $this->cleaningService->runFullPipeline($mainFilePath, $referenceFiles, $options);
            }

            // Increment usage counter
            User::where('id', $user->id)->increment('files_used_this_month', 1);

            // Build download URLs
            $downloadUrls = [];
            if (!empty($result['cleaned_file_path'])) {
                $downloadUrls['cleaned'] = route('datasets.download', [
                    'filename' => basename($result['cleaned_file_path'])
                ]);
            }
            if (!empty($result['enriched_file'])) {
                $downloadUrls['enriched'] = route('datasets.download', [
                    'filename' => basename($result['enriched_file'])
                ]);
            }
            if (!empty($result['report_file'])) {
                $downloadUrls['report'] = route('datasets.download', [
                    'filename' => basename($result['report_file'])
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'File processed successfully!',
                'data' => $result,
                'download_urls' => $downloadUrls,
                'pipeline_mode' => $pipelineMode
            ]);

        } catch (\Exception $e) {
            Log::error('Upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download cleaned/enriched file
     */
    public function download(Request $request, $filename)
    {
        $userId = Auth::id() ?? 'shared';
        
        // Check in multiple possible locations
        $possiblePaths = [
            '/shared_data/cleaned/' . $userId . '/' . $filename,    // ← FIX
            '/shared_data/results/' . $userId . '/' . $filename,    // ← FIX
        ];
        $path = null;
        foreach ($possiblePaths as $possiblePath) {
            if (file_exists($possiblePath)) {
                $path = $possiblePath;
                break;
            }
        }

        if (!$path) {
            abort(404, 'File not found: ' . $filename);
        }

        $alias = $request->route('alias') ?? basename($filename);

        return response()->download($path, $alias);
    }

    /**
     * Handle batch upload
     */
    public function batchUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:51200',
            'use_llm_enricher' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $plan = $user->plan;

            if (!$request->hasFile('files')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No files uploaded.'
                ], 400);
            }

            $files = $request->file('files');
            $isPro = $user->plan?->slug === 'pro';

            if (count($files) > $plan->max_files_per_transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => $isPro 
                        ? 'You reached the Pro batch limit (10 files per upload). Contact support for enterprise limits.'
                        : 'Too many files for your current plan.'
                ], 403);
            }

            if (!$user->canUpload(count($files))) {
                return response()->json([
                    'status' => 'error',
                    'message' => $isPro 
                        ? 'You reached our fair-use security limit. Please contact support to increase it.'
                        : 'Monthly limit reached. Please upgrade your plan.'
                ], 403);
            }

            // Validate total size
            $totalBytes = array_sum(array_map(fn($f) => $f->getSize(), $files));
            $maxTotalBytes = $plan->max_total_mb_per_transaction * 1024 * 1024;

            if ($totalBytes > $maxTotalBytes) {
                return response()->json([
                    'status' => 'error',
                    'message' => $isPro
                        ? 'You reached the Pro upload size limit. Contact support for higher limits.'
                        : 'Total upload size exceeds the maximum allowed for your plan.'
                ], 403);
            }

            // Store files in shared_data
            $userId = Auth::id();
            $uploadDir = '/shared_data/uploads/' . $userId;
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $storagePaths = [];
            $invalidFiles = [];

            foreach ($files as $file) {
                if (!$this->cleaningService->isFormatSupported($file->getClientOriginalName())) {
                    $invalidFiles[] = $file->getClientOriginalName();
                    continue;
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $uploadDir . '/' . $fileName;
                $file->move($uploadDir, $fileName);
                @chmod($filePath, 0666);
                $storagePaths[] = $filePath;
            }

            if (!empty($invalidFiles)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some files have unsupported formats.',
                    'invalid_files' => $invalidFiles
                ], 422);
            }

            $options = [
                'no_llm_enricher' => !$request->boolean('use_llm_enricher', true)
            ];

            $results = $this->cleaningService->cleanBatch($storagePaths, $options);

            User::where('id', $user->id)->increment('files_used_this_month', count($files));

            return response()->json([
                'status' => 'success',
                'message' => 'Your data has been processed',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Batch upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'We could not process your files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show all processed files
     */
    public function showFiles()
    {
        $userId = Auth::id();
            $paths = [
            '/shared_data/cleaned/' . $userId,      // ← FIX
            '/shared_data/results/' . $userId,      // ← FIX
        ];

        $fileList = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = File::files($path);

            foreach ($files as $file) {
                $fileList[] = [
                    'name' => $file->getFilename(),
                    'size' => round($file->getSize() / 1024, 2) . ' KB',
                    'modified' => \Carbon\Carbon::createFromTimestamp($file->getMTime())->diffForHumans(),
                    'type' => $this->getFileType($file->getFilename()),
                    'download_url' => route('datasets.download', [
                        'filename' => $file->getFilename(),
                        'alias' => basename($file)
                    ])
                ];
            }
        }

        return view('datasets.files', compact('fileList'));
    }

    /**
     * Determine file type for display
     */
    private function getFileType(string $filename): string
    {
        if (str_contains($filename, '_CLEANED.csv')) {
            return 'cleaned';
        } elseif (str_contains($filename, '_ENRICHED.csv')) {
            return 'enriched';
        } elseif (str_contains($filename, '_REPORT.json')) {
            return 'report';
        }
        return 'unknown';
    }
}