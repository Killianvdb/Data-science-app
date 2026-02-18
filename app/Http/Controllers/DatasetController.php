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
        return view('datasets.index', compact('supportedFormats'));
    }


    /**
     * Handle file upload and cleaning
     */
    public function upload(Request $request)
    {
        // 1. Validate the input
        $request->validate([
            //'file' => 'required|file|mimes:xlsx,xls,csv,txt,json,xml|max:20480', // 20MB limit
            'row_threshold' => 'nullable|numeric|min:0|max:1',
            'col_threshold' => 'nullable|numeric|min:0|max:1',
            'imputation_type' => 'nullable|string|in:RDF,KNN,mean,median,most_frequent',
            'special_characters' => 'nullable|string',
            'action' => 'nullable|string|in:add,remove',
        ]);

        try {
            $file = $request->file('file');

            /** @var \App\Models\User $user */
            $user = Auth::user();
            $plan = $user->plan;

            $maxSizeBytes = $plan->max_file_size_mb * 1024 * 1024;

            if ($file->getSize() > $maxSizeBytes) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File exceeds maximum size for your plan.'
                ], 403);
            }

            if (!$user->canUpload(1)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Monthly limit reached. Please upgrade your plan.'
                ], 403);
            }

            // 2. Store the file with a hashed name (Security)
            //$storagePath = $file->store('data_mission');
            $storagePath = $file->store('data_mission/' . Auth::id());


            // 3. Build the options array to pass to Python
            $options = [
                'row_threshold' => (float) ($request->input('row_threshold', 0.8)),
                'col_threshold' => (float) ($request->input('col_threshold', 0.8)),
                'imputation_type' => $request->input('imputation_type', 'RDF'),
            ];

            // Convert the comma-separated string from the UI into a Python-friendly array
            if ($request->filled('special_characters')) {
                // Clean up whitespace and split by comma
                $chars = array_map('trim', explode(',', $request->special_characters));
                $options['special_character'] = $chars;
            }

            if ($request->filled('action')) {
                $options['action'] = $request->action;
            }

            // 4. Run the cleaning service
            $result = $this->cleaningService->cleanUploadedFile($storagePath, $options);


            User::where('id', $user->id)->increment('files_used_this_month', 1);

            // 5. Return success with the download URL
            return response()->json([
                'status' => 'success',
                'message' => 'File cleaned and converted successfully!',
                'data' => $result,
                'download_url' => route('datasets.download', [
                    'filename' => basename($result['cleaned_file_path'])
                ])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clean file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download cleaned file
     */
    public function download(Request $request, $filename)
    {
        //$path = storage_path('app/cleaned_output/' . $filename);
        $path = storage_path('app/cleaned_output/' . Auth::id() . '/' . $filename);

        $alias = $request->route('alias') ?? 'cleaned_data.csv';

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        // The second argument in download() is the name the user actually sees
        return response()->download($path, $alias);
    }

    /**
     * Handle batch upload
     */
    public function batchUpload(Request $request)
    {

    //dd($request->all(), $request->file('files'));

        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:51200',
            'row_threshold' => 'nullable|numeric|min:0|max:1',
            'col_threshold' => 'nullable|numeric|min:0|max:1',
            'imputation_type' => 'nullable|string|in:RDF,KNN,mean,median,most_frequent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $storagePaths = [];

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


            if (count($files) > $plan->max_files_per_transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Too many files for your current plan.'
                ], 403);
            }

            if (!$user->canUpload(count($files))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Monthly limit reached. Please upgrade your plan.'
                ], 403);
            }

            foreach ($files as $file) {
                if ($file->getSize() > $plan->max_file_size_mb * 1024 * 1024) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'One or more files exceed maximum size for your plan.'
                    ], 403);
                }
            }

            $invalidFiles = [];

            foreach ($files as $file) {
                if (!$this->cleaningService->isFormatSupported($file->getClientOriginalName())) {
                    $invalidFiles[] = $file->getClientOriginalName();
                    continue;
                }

                $storagePaths[] = $file->store('data_mission/' . Auth::id());
            }

            if (!empty($invalidFiles)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some files have unsupported formats.',
                    'invalid_files' => $invalidFiles
                ], 422);
            }

            $options = [];
            if ($request->filled('row_threshold')) {
                $options['row_threshold'] = (float) $request->row_threshold;
            }
            if ($request->filled('col_threshold')) {
                $options['col_threshold'] = (float) $request->col_threshold;
            }
            if ($request->filled('imputation_type')) {
                $options['imputation_type'] = $request->imputation_type;
            }

            $results = $this->cleaningService->cleanBatch($storagePaths, $options);


            User::where('id', $user->id)->increment('files_used_this_month', count($files));

            return response()->json([
                'status' => 'success',
                'message' => 'Batch processing completed',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Batch processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all cleaned files
     */
    public function listCleaned()
    {
        //$files = Storage::files('cleaned_output');
        $files = Storage::files('cleaned_output/' . Auth::id());


        $fileList = array_map(function($file) {
            return [
                'name' => basename($file),
                'size' => Storage::size($file),
                'modified' => Storage::lastModified($file),
                'download_url' => route('datasets.download', ['filename' => basename($file)])
            ];
        }, $files);

        return response()->json([
            'status' => 'success',
            'files' => $fileList
        ]);
    }

    public function showFiles()
    {
        // Try reading directly from the filesystem instead
        //$path = storage_path('app/cleaned_output');
        $path = storage_path('app/cleaned_output/' . Auth::id());

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $files = File::files($path); // Use File facade instead of Storage

        $fileList = array_map(function($file) {
            return [
                'name' => $file->getFilename(),
                'size' => round($file->getSize() / 1024, 2) . ' KB',
                'modified' => \Carbon\Carbon::createFromTimestamp($file->getMTime())->diffForHumans(),
                'download_url' => route('datasets.download', [
                    'filename' => $file->getFilename(),
                    'alias' => basename($file)
                ])
            ];
        }, $files);

        return view('datasets.files', compact('fileList'));
    }

}
