<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\DatasetController;


//without auth

Route::get('/', function () {
    return view('welcome');
});

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');


Route::get('/how-it-works', function () {
    return view('how-it-works');
})->name('how-it-works');


Route::get('/contact', function() {
    return view('contact');
})->name('contact');

Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');



// with authentication

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/upload', [UploadController::class, 'create'])->name('upload.create');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');

    Route::get('/my-datasets', fn () => view('datasets.index'))
        ->middleware('verified') // the email needs to be verified
        ->name('datasets.index');


    Route::get('/', [DatasetController::class, 'create'])->name('datasets.create');

    Route::get('/datasets/create', [DatasetController::class, 'create'])
        ->name('datasets.create');

    Route::post('/datasets', [DatasetController::class, 'store'])
        ->name('datasets.store');

    Route::get('/my-datasets', [DatasetController::class, 'index'])
        ->name('datasets.index');

        

});





require __DIR__.'/auth.php';
