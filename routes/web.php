
  <?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VisualizationController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;

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

Route::middleware('auth')->group(function () {

    // Visualization Routes
    Route::get('/visualise', [VisualizationController::class, 'index'])->name('visualise.index');
    Route::post('/visualise/generate', [VisualizationController::class, 'generate'])->name('visualise.generate');
    Route::get('/visualise/{id}', [VisualizationController::class, 'show'])->name('visualise.show');

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/upload', [UploadController::class, 'create'])->name('upload.create');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');

    Route::prefix('datasets')->name('datasets.')->group(function () {
        // Pages
        Route::get('/', [DatasetController::class, 'index'])->name('index')->middleware('verified');
        Route::get('/files', [DatasetController::class, 'showFiles'])->name('files');

        // File Upload & Processing
        Route::post('/upload', [DatasetController::class, 'upload'])->name('upload');
        Route::post('/batch-upload', [DatasetController::class, 'batchUpload'])->name('batchUpload');

        // File Download
        Route::get('/download/{filename}/{alias?}', [DatasetController::class, 'download'])->name('download');
    });

        // for the visualisation

        Route::get('/visualise', [VisualizationController::class, 'index'])->name('visualise.index');
        Route::post('/visualise', [VisualizationController::class, 'generate'])->name('visualise.generate');

        Route::get('/report/{id}', [VisualizationController::class, 'show'])->name('visualise.show');
        Route::post('/report/{id}', [VisualizationController::class, 'update'])->name('visualise.update');


        // Optional: Add these new routes for better UX
        // API endpoint to check processing status (if you implement async processing later)
        // Route::get('/status/{jobId}', [DatasetController::class, 'checkStatus'])->name('status');

        // API endpoint to get file metadata
        // Route::get('/metadata/{filename}', [DatasetController::class, 'getMetadata'])->name('metadata');
    });

    // Payments and subscriptions
    Route::get('/pricing', [SubscriptionController::class, 'index'])->name('pricing');
    Route::post('/subscription/change', [SubscriptionController::class, 'change'])->name('subscription.change');

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
    });

require __DIR__.'/auth.php';
