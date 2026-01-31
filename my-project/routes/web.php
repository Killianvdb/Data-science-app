<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DataCleaningController;


Route::get('/', function () {
    return view('welcome');
});


Route::prefix('data-cleaning')->name('data-cleaning.')->group(function () {
    // Show upload form
    Route::get('/', [DataCleaningController::class, 'index'])->name('index');
    
    // Handle single file upload
    Route::post('/upload', [DataCleaningController::class, 'upload'])->name('upload');
    
    // Handle batch upload
    Route::post('/batch-upload', [DataCleaningController::class, 'batchUpload'])->name('batch-upload');
    
    // Download cleaned file - UPDATED to accept optional alias
    Route::get('/download/{filename}/{alias?}', [DataCleaningController::class, 'download'])->name('download');
    
    // List all cleaned files
    Route::get('/files', [DataCleaningController::class, 'listCleaned'])->name('files');
});