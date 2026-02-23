<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dataset;
use App\Models\User;
use App\Services\DataCleaningService;
use App\Services\RulesConverterService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DatasetController extends Controller
{
    protected DataCleaningService  $cleaningService;
    protected RulesConverterService $rulesConverter;

    public function __construct(
        DataCleaningService   $cleaningService,
        RulesConverterService $rulesConverter
    ) {
        $this->cleaningService = $cleaningService;
        $this->rulesConverter  = $rulesConverter;
    }

    // =========================================================================
    // ALLOWED OUTPUT DIRECTORIES
    // =========================================================================

    protected function allowedOutputDirs(int $userId): array
    {
        return [
            '/shared_data/cleaned/' . $userId,
            '/shared_data/results/' . $userId,
            '/shared_data/merged/'  . $userId,
        ];
    }

    // =========================================================================
    // SECURE FILENAME HELPERS
    // =========================================================================

    /**
     * Collision-safe filename prefix using microsecond uniqid + random suffix.
     */
    protected function uniquePrefix(): string
    {
        return uniqid('', true) . '_' . Str::random(6);
    }

    /**
     * Validate a download filename and resolve it to a safe absolute path.
     * Three-layer defence: basename() → regex whitelist → realpath() confinement.
     * Returns null if any check fails.
     */
    protected function resolveDownloadPath(string $rawFilename, int $userId): ?string
    {
        $filename = basename($rawFilename);

        // Whitelist: only our generated filenames (.csv and .json reports, .pdf reports)
        if (!preg_match('/^[\w\-. ]+\.(csv|json|pdf)$/i', $filename)) {
            Log::warning('Download rejected: invalid filename pattern', [
                'raw'     => $rawFilename,
                'cleaned' => $filename,
                'user_id' => $userId,
            ]);
            return null;
        }

        foreach ($this->allowedOutputDirs($userId) as $dir) {
            $candidate = $dir . '/' . $filename;
            $real      = realpath($candidate);
            $realDir   = realpath($dir);
            if ($real !== false && $realDir !== false
                && str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        Log::warning('Download rejected: file not in allowed dirs', [
            'filename' => $filename,
            'user_id'  => $userId,
        ]);
        return null;
    }

    // =========================================================================
    // SHOW UPLOAD FORM
    // =========================================================================

    public function index()
    {
        $supportedFormats = $this->cleaningService->getSupportedFormats();
        $datasets         = Dataset::where('user_id', Auth::id())->get();
        $planSlug         = Auth::user()->plan?->slug;

        return view('datasets.index', compact('supportedFormats', 'planSlug'));
    }

    // =========================================================================
    // SINGLE FILE UPLOAD  (context form + optional reference files)
    // =========================================================================

    public function upload(Request $request)
    {
        // ── Merge validation rules: file upload + context form ────────────────
        $request->validate(array_merge(
            [
                'file'              => 'required|file|mimes:xlsx,xls,csv,txt,json,xml|max:20480',
                'reference_files.*' => 'nullable|file|mimes:xlsx,xls,csv|max:10240',
                'pipeline_mode'     => 'nullable|string|in:clean_only,full_pipeline',
                'use_llm_enricher'  => 'nullable|boolean',
            ],
            RulesConverterService::validationRules()
        ));

        try {
            /** @var \App\Models\User $user */
            $user  = Auth::user();
            $plan  = $user->plan;
            $isPro = $user->plan?->slug === 'pro';

            if (!$plan) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No subscription plan found for this user.',
                ], 403);
            }

            $file          = $request->file('file');
            $maxTotalBytes = $plan->max_total_mb_per_transaction * 1024 * 1024;

            if ($file->getSize() > $maxTotalBytes) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $isPro
                        ? 'This file exceeds the current Pro max size (20 MB). Contact support if you need a higher limit.'
                        : 'File exceeds maximum size for your plan.',
                ], 403);
            }

            if (!$user->canUpload(1)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $isPro
                        ? 'You reached our fair-use security limit. Please contact support to increase it.'
                        : 'Monthly limit reached. Please upgrade your plan.',
                ], 403);
            }

            // ── Store main file ───────────────────────────────────────────────
            $userId    = Auth::id();
            $uploadDir = '/shared_data/uploads/' . $userId;
            $this->ensureDir($uploadDir);

            $prefix       = $this->uniquePrefix();
            $mainFileName = $prefix . '_' . $file->getClientOriginalName();
            $mainFilePath = $uploadDir . '/' . $mainFileName;
            $file->move($uploadDir, $mainFileName);
            @chmod($mainFilePath, 0666);

            // ── Store reference files ─────────────────────────────────────────
            $referenceFiles = [];
            if ($request->hasFile('reference_files')) {
                foreach ($request->file('reference_files') as $refFile) {
                    $refName = $this->uniquePrefix() . '_ref_' . $refFile->getClientOriginalName();
                    $refPath = $uploadDir . '/' . $refName;
                    $refFile->move($uploadDir, $refName);
                    @chmod($refPath, 0666);
                    $referenceFiles[] = $refPath;
                }
            }

            // ── Build options ─────────────────────────────────────────────────
            $options = [
                'no_llm_enricher' => !$request->boolean('use_llm_enricher', true),
            ];

            // Context form → rules.json  (replaces manual rules_file upload)
            $contextFormData = $request->only([
                'dataset_description',
                'no_negative_cols',
                'identifier_cols',
                'required_cols',
                'range_rules',
                'flag_only',
            ]);

            // Strip empty strings that blank form inputs submit as ''
            foreach (['no_negative_cols', 'identifier_cols', 'required_cols'] as $key) {
                if (isset($contextFormData[$key]) && is_array($contextFormData[$key])) {
                    $contextFormData[$key] = array_values(
                        array_filter($contextFormData[$key], fn($v) => trim((string) $v) !== '')
                    );
                }
            }
            // Strip range rows where column is empty
            if (isset($contextFormData['range_rules']) && is_array($contextFormData['range_rules'])) {
                $contextFormData['range_rules'] = array_values(
                    array_filter($contextFormData['range_rules'], fn($r) => trim((string) ($r['column'] ?? '')) !== '')
                );
            }

            $hasContextData = $this->hasAnyContextData($contextFormData);
            if ($hasContextData) {
                $rulesPath = $this->rulesConverter->writeRulesFile(
                    $contextFormData,
                    $uploadDir,
                    $this->uniquePrefix()
                );
                $options['rules_file'] = $rulesPath;
                Log::info('[Upload] Context form converted to rules.json', [
                    'rules_path' => $rulesPath,
                    'rule_count' => count($this->rulesConverter->convert($contextFormData)['rules']),
                ]);
            }

            // ── Run pipeline ──────────────────────────────────────────────────
            $pipelineMode = $request->input('pipeline_mode', 'full_pipeline');

            if ($pipelineMode === 'clean_only' || empty($referenceFiles)) {
                $result = $this->cleaningService->cleanUploadedFile($mainFilePath, $options);
            } else {
                $result = $this->cleaningService->runFullPipeline(
                    $mainFilePath, $referenceFiles, $options
                );
            }

            // ── Generate PDF report ───────────────────────────────────────────
            $pdfUrl = null;
            if (!empty($result['report_file']) && file_exists($result['report_file'])) {
                try {
                    $pdfPath = $this->cleaningService->generatePdfReport(
                        $result['report_file'],
                        $userId
                    );
                    if ($pdfPath && file_exists($pdfPath)) {
                        $result['report_pdf'] = $pdfPath;
                        $pdfUrl = route('datasets.download', [
                            'filename' => basename($pdfPath),
                        ]);
                    }
                } catch (\Exception $e) {
                    // PDF generation is non-critical — log but don't fail the request
                    Log::warning('[Upload] PDF report generation failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // ── Increment usage ───────────────────────────────────────────────
            User::where('id', $user->id)->increment('files_used_this_month', 1);

            // ── Build download URLs ───────────────────────────────────────────
            $downloadUrls = [];
            $fileMap = [
                'cleaned_file_path' => 'cleaned',
                'enriched_file'     => 'enriched',
                'report_file'       => 'report',
            ];
            foreach ($fileMap as $key => $label) {
                if (!empty($result[$key])) {
                    $downloadUrls[$label] = route('datasets.download', [
                        'filename' => basename($result[$key]),
                    ]);
                }
            }
            if ($pdfUrl) {
                $downloadUrls['report_pdf'] = $pdfUrl;
            }

            return response()->json([
                'status'        => 'success',
                'message'       => 'File processed successfully!',
                'data'          => $result,
                'download_urls' => $downloadUrls,
                'pipeline_mode' => $pipelineMode,
                'context_rules_applied' => $hasContextData,
            ]);

        } catch (\Exception $e) {
            Log::error('Upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to process file: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // SECURE FILE DOWNLOAD
    // =========================================================================

    public function download(Request $request, string $filename)
    {
        $userId = Auth::id();
        $path   = $this->resolveDownloadPath($filename, $userId);

        if (!$path) {
            abort(404, 'File not found.');
        }

        $alias = $request->query('alias', basename($path));
        return response()->download($path, $alias);
    }

    // =========================================================================
    // BATCH UPLOAD
    // =========================================================================

    public function batchUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files'            => 'required|array|min:1',
            'files.*'          => 'required|file|max:102400',
            'use_llm_enricher' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            /** @var \App\Models\User $user */
            $user  = Auth::user();
            $plan  = $user->plan;
            $isPro = $user->plan?->slug === 'pro';

            if (!$request->hasFile('files')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No files uploaded.',
                ], 400);
            }

            $files = $request->file('files');

            if (count($files) > $plan->max_files_per_transaction) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $isPro
                        ? 'You reached the Pro batch limit (10 files per upload). Contact support for enterprise limits.'
                        : 'Too many files for your current plan.',
                ], 403);
            }

            if (!$user->canUpload(count($files))) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $isPro
                        ? 'You reached our fair-use security limit. Please contact support to increase it.'
                        : 'Monthly limit reached. Please upgrade your plan.',
                ], 403);
            }

            $totalBytes    = array_sum(array_map(fn ($f) => $f->getSize(), $files));
            $maxTotalBytes = $plan->max_total_mb_per_transaction * 1024 * 1024;

            if ($totalBytes > $maxTotalBytes) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $isPro
                        ? 'You reached the Pro upload size limit. Contact support for higher limits.'
                        : 'Total upload size exceeds the maximum allowed for your plan.',
                ], 403);
            }

            $userId    = Auth::id();
            $uploadDir = '/shared_data/uploads/' . $userId;
            $this->ensureDir($uploadDir);

            $storagePaths = [];
            $invalidFiles = [];

            foreach ($files as $file) {
                if (!$this->cleaningService->isFormatSupported($file->getClientOriginalName())) {
                    $invalidFiles[] = $file->getClientOriginalName();
                    continue;
                }
                $fileName = $this->uniquePrefix() . '_' . $file->getClientOriginalName();
                $filePath = $uploadDir . '/' . $fileName;
                $file->move($uploadDir, $fileName);
                @chmod($filePath, 0666);
                $storagePaths[] = $filePath;
            }

            if (!empty($invalidFiles)) {
                return response()->json([
                    'status'        => 'error',
                    'message'       => 'Some files have unsupported formats.',
                    'invalid_files' => $invalidFiles,
                ], 422);
            }

            $options = [
                'no_llm_enricher' => !$request->boolean('use_llm_enricher', true),
            ];

            $results = $this->cleaningService->cleanBatch($storagePaths, $options);

            User::where('id', $user->id)->increment('files_used_this_month', count($files));

            return response()->json([
                'status'  => 'success',
                'message' => 'Your data has been processed.',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Batch upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'We could not process your files: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // SHOW PROCESSED FILES
    // =========================================================================

    public function showFiles()
    {
        $userId   = Auth::id();
        $fileList = [];

        foreach ($this->allowedOutputDirs($userId) as $path) {
            if (!is_dir($path)) continue;

            foreach (File::files($path) as $file) {
                $fileList[] = [
                    'name'         => $file->getFilename(),
                    'size'         => round($file->getSize() / 1024, 2) . ' KB',
                    'modified'     => \Carbon\Carbon::createFromTimestamp($file->getMTime())->diffForHumans(),
                    'type'         => $this->getFileType($file->getFilename()),
                    'download_url' => route('datasets.download', [
                        'filename' => $file->getFilename(),
                    ]),
                ];
            }
        }

        return view('datasets.files', compact('fileList'));
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Returns true if the user filled in at least one context form field.
     */
    private function hasAnyContextData(array $contextFormData): bool
    {
        if (!empty(trim($contextFormData['dataset_description'] ?? ''))) return true;
        if (!empty($contextFormData['no_negative_cols']))                return true;
        if (!empty($contextFormData['identifier_cols']))                 return true;
        if (!empty($contextFormData['required_cols']))                   return true;
        if (!empty($contextFormData['range_rules']))                     return true;
        return false;
    }

    private function getFileType(string $filename): string
    {
        if (str_contains($filename, '_CLEANED.csv'))  return 'cleaned';
        if (str_contains($filename, '_ENRICHED.csv')) return 'enriched';
        if (str_contains($filename, '_REPORT.json'))  return 'report';
        if (str_contains($filename, '_REPORT.pdf'))   return 'report_pdf';
        return 'unknown';
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}