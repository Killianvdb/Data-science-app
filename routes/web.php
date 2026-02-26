<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VisualizationController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\ConvertController;
use App\Http\Controllers\CsvImportController;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('datasets.index');
});

Route::get('/privacy-policy', fn() => view('privacy-policy'))->name('privacy.policy');
Route::get('/how-it-works', fn() => view('how-it-works'))->name('how-it-works');

Route::get('/contact', fn() => view('contact'))->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::prefix('ai-chat')->name('ai-chat.')->group(function () {
    Route::get('/',            [AiChatController::class, 'index'])      ->name('index');
    Route::post('/chat',       [AiChatController::class, 'chat'])       ->name('chat');
    Route::post('/upload',     [AiChatController::class, 'uploadCsv'])  ->name('upload');
    Route::post('/clear',      [AiChatController::class, 'clearHistory'])->name('clear');
    Route::post('/remove-file',[AiChatController::class, 'removeFile']) ->name('remove-file');
    Route::post('/clear-all',  [AiChatController::class, 'clearAll'])   ->name('clear-all');
});
Route::middleware('auth')->group(function () {


    Route::get('/import', [CsvImportController::class, 'form'])->name('csv.form');
    Route::post('/import', [CsvImportController::class, 'import'])->name('csv.import');
    Route::get('/dashboard', [CsvImportController::class, 'dashboard'])->name('csv.dashboard');

    // Visualization Routes
    Route::get('/visualise', [VisualizationController::class, 'index'])->name('visualise.index');
    Route::get('/visualise/from-cleaned/{filename}', [VisualizationController::class, 'fromCleaned'])->name('visualise.fromCleaned')->where('filename', '.+');

    Route::post('/visualise/generate', [VisualizationController::class, 'generate'])->name('visualise.generate');
    Route::get('/visualise/{id}', [VisualizationController::class, 'show'])->name('visualise.show');


    Route::get('/import/from-cleaned/{filename}', [CsvImportController::class, 'fromCleaned'])->name('csv.fromCleaned')->where('filename', '.+');
    Route::get('/convert', [ConvertController::class, 'index'])->name('convert.index');
    Route::post('/convert', [ConvertController::class, 'convert'])->name('convert.convert');
    Route::get('/convert/download/{job}/{file}', [ConvertController::class, 'download'])->whereUuid('job')->name('convert.download');

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/upload', [UploadController::class, 'create'])->name('upload.create');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
    Route::get('/datasets/jobs/{id}/status', [DatasetController::class, 'jobStatus'])->name('datasets.job.status');

    Route::prefix('datasets')->name('datasets.')->group(function () {
        // Pages
        Route::get('/', [DatasetController::class, 'index'])->name('index')->middleware('verified');
        Route::get('/files', [DatasetController::class, 'showFiles'])->name('files');

        // File Upload & Processing
        Route::post('/upload', [DatasetController::class, 'upload'])->name('upload');
        Route::post('/batch-upload', [DatasetController::class, 'batchUpload'])->name('batchUpload');

        // File Download
        Route::get('/download/{filename}/{alias?}', [DatasetController::class, 'download'])->name('download');

        // Optional: Add these new routes for better UX
        // API endpoint to check processing status (if you implement async processing later)
        // Route::get('/status/{jobId}', [DatasetController::class, 'checkStatus'])->name('status');

        // API endpoint to get file metadata
        // Route::get('/metadata/{filename}', [DatasetController::class, 'getMetadata'])->name('metadata');
    });

    // Payments and subscriptions
    Route::get('/pricing', [SubscriptionController::class, 'index'])->name('pricing');
    //Route::post('/subscription/change', [SubscriptionController::class, 'change'])->name('subscription.change');
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])
        ->name('subscription.checkout');

    Route::get('/subscription/success', [SubscriptionController::class, 'success'])
        ->name('subscription.success');

    Route::get('/subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->name('subscription.cancel');

    Route::get('/billing/portal', function (\Illuminate\Http\Request $request) {
        return $request->user()->redirectToBillingPortal(route('pricing'));
    })->name('billing.portal');



    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function(){
        Route::get('users', [UserController::class,'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserController::class,'updateRole'])->name('users.updateRole');
        Route::delete('users/{user}', [UserController::class,'destroy'])->name('users.destroy');
        Route::get('users/search', [UserController::class,'search'])->name('users.search');
        Route::patch('users/{user}/plan', [UserController::class,'updatePlan'])->name('users.plan');
    });
});


Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);





require __DIR__.'/auth.php';
