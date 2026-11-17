<?php

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\GenerationEstimateController;
use App\Http\Controllers\GenerationVersionController;
use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('studio', StudioController::class)->name('studio');

    Route::post('generations/estimate', GenerationEstimateController::class)
        ->middleware('throttle:120,1')
        ->name('generations.estimate');

    Route::post('generations/{generation}/versions', GenerationVersionController::class)
        ->middleware('throttle:20,1')
        ->name('generations.versions.store');

    Route::resource('generations', GenerationController::class)
        ->only(['index', 'store', 'show'])
        ->middlewareFor('store', 'throttle:20,1');
});

require __DIR__.'/settings.php';
