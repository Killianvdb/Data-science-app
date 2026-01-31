<?php

namespace App\Http\Controllers;

use App\Services\DataCleaningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; 


class DataCleaningController extends Controller
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
        return view('data-cleaning.index', compact('supportedFormats'));
    }

    /**
     * Handle file upload and cleaning
     */
    public function upload(Request $request)
    {
        // 1. Validate the input
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt,json,xml|max:20480', // 20MB limit
            'row_threshold' => 'nullable|numeric|min:0|max:1',
            'col_threshold' => 'nullable|numeric|min:0|max:1',
            'imputation_type' => 'nullable|string|in:RDF,KNN,mean,median,most_frequent',
            'special_characters' => 'nullable|string',
            'action' => 'nullable|string|in:add,remove',
        ]);

        try {
            $file = $request->file('file');
            
            // 2. Store the file with a hashed name (Security)
            $storagePath = $file->store('data_mission');

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

            // 5. Return success with the download URL
            return response()->json([
                'status' => 'success',
                'message' => 'File cleaned and converted successfully!',
                'data' => $result,
                'download_url' => route('data-cleaning.download', [
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
        $path = storage_path('app/cleaned_output/' . $filename);
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
        $validator = Validator::make($request->all(), [
            'files.*' => [
                'required',
                'file',
                'max:51200',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $storagePaths = [];
            foreach ($request->file('files') as $file) {
                if ($this->cleaningService->isFormatSupported($file->getClientOriginalName())) {
                    $storagePaths[] = $file->store('data_mission');
                }
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
        $files = Storage::files('cleaned_output');
        
        $fileList = array_map(function($file) {
            return [
                'name' => basename($file),
                'size' => Storage::size($file),
                'modified' => Storage::lastModified($file),
                'download_url' => route('data-cleaning.download', ['filename' => basename($file)])
            ];
        }, $files);

        return response()->json([
            'status' => 'success',
            'files' => $fileList
        ]);
    }
}