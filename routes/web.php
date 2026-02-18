<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\UploadController;
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

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/upload', [UploadController::class, 'create'])->name('upload.create');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');


    Route::prefix('datasets')->name('datasets.')->group(function () {

        // Pages
        Route::get('/', [DatasetController::class, 'index'])->name('index')->middleware('verified');

        // Actions
        Route::post('/upload', [DatasetController::class, 'upload'])->name('upload');
        Route::post('/batch-upload', [DatasetController::class, 'batchUpload'])->name('batchUpload');

        // File Management
        Route::get('/files', [DatasetController::class, 'showFiles'])->name('files');
        Route::get('/download/{filename}/{alias?}', [DatasetController::class, 'download'])->name('download');
    });

    //payments and subscriptions
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


});

require __DIR__.'/auth.php';
