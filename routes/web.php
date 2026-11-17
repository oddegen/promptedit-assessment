<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\GenerationEstimateController;
use App\Http\Controllers\GenerationVersionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StudioController;
use App\Http\Middleware\RedirectAdminsToReport;
use App\Http\Middleware\RedirectCustomersToStudio;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('draft', DraftController::class)
    ->middleware('throttle:30,1')
    ->name('draft.store');

Route::middleware(['auth', 'verified', RedirectAdminsToReport::class])->group(function () {
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

    Route::resource('credits', CreditController::class)
        ->only(['index', 'store'])
        ->middlewareFor('store', 'throttle:10,1');
});

Route::get('admin/report', ReportController::class)
    ->middleware(['auth', 'verified', RedirectCustomersToStudio::class, 'can:admin'])
    ->name('admin.report');

require __DIR__.'/settings.php';
