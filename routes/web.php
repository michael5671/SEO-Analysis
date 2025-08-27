<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyzeController;

Route::get('/', [AnalyzeController::class, 'form'])->name('form');
Route::post('/analyze', [AnalyzeController::class, 'analyze'])->name('analyze');
Route::get('/runs/{id}', [AnalyzeController::class, 'show'])->name('runs.show');

use App\Http\Controllers\RelevanceController;
Route::get('/runs/{runId}/relevance/compute', [RelevanceController::class, 'compute'])->name('relevance.compute');
Route::get('/runs/{runId}/relevance', [RelevanceController::class, 'show'])->name('relevance.show');
Route::get('/runs/{runId}/relevance/top', [RelevanceController::class,'topPerPaa'])->name('relevance.top');
