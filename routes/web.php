<?php

use Illuminate\Support\Facades\Route;
use NDEstates\LaravelModelSchemaChecker\Http\Controllers\ModelSchemaCheckerController;

/*
|--------------------------------------------------------------------------
| Model Schema Checker Web Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the ModelSchemaCheckerServiceProvider when
| the package is installed. All routes are protected by authentication
| middleware to ensure only authorized users can access the dashboard.
|
*/

Route::middleware(['web', 'auth'])->prefix('model-schema-checker')->name('model-schema-checker.')->group(function () {

    // Main dashboard
    Route::get('/', [ModelSchemaCheckerController::class, 'index'])->name('index');

    // Run checks
    Route::post('/run-checks', [ModelSchemaCheckerController::class, 'runChecks'])->name('run-checks');

    // View results
    Route::get('/results/{result}', [ModelSchemaCheckerController::class, 'showResult'])->name('results.show');

    // Apply fixes
    Route::post('/apply-fixes', [ModelSchemaCheckerController::class, 'applyFixes'])->name('apply-fixes');

    // Step-by-step fixes
    Route::get('/step-by-step/{result}', [ModelSchemaCheckerController::class, 'stepByStep'])->name('step-by-step');
    Route::post('/apply-step-fix', [ModelSchemaCheckerController::class, 'applyStepFix'])->name('apply-step-fix');

    // Rollback fixes
    Route::post('/rollback-fixes', [ModelSchemaCheckerController::class, 'rollbackFixes'])->name('rollback-fixes');

    // AJAX endpoints for real-time updates
    Route::get('/check-progress/{jobId}', [ModelSchemaCheckerController::class, 'checkProgress'])->name('check-progress');
    Route::get('/results-data/{result}', [ModelSchemaCheckerController::class, 'getResultsData'])->name('results-data');

    // History and reports
    Route::get('/history', [ModelSchemaCheckerController::class, 'history'])->name('history');
    Route::get('/reports/{result}/download', [ModelSchemaCheckerController::class, 'downloadReport'])->name('reports.download');

});