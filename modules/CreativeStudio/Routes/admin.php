<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\CreativeStudio\Http\Controllers\Admin;

Route::prefix('creative-studio')->name('creative-studio.')->group(function (): void {
    Route::get('/', Admin\CampaignsGetController::class)->name('index');
    Route::get('/sources', Admin\SourcesGetController::class)->name('sources');
    Route::post('/campaigns', Admin\CampaignPostController::class)->name('create');
    Route::get('/campaigns/{campaign}', Admin\CampaignGetController::class)->name('show');
    Route::put('/campaigns/{campaign}/brief', Admin\CampaignBriefPutController::class)->name('brief');
    Route::put('/campaigns/{campaign}/dates', Admin\CampaignDatesPutController::class)->name('dates');
    Route::put('/campaigns/{campaign}/budget', Admin\CampaignBudgetPutController::class)->name('budget');
    Route::put('/campaigns/{campaign}/brand', Admin\BrandPutController::class)->name('brand');
    Route::post('/campaigns/{campaign}/revisions', Admin\RevisionPostController::class)->name('revisions.create');
    Route::post('/campaigns/{campaign}/generate', Admin\GenerationPostController::class)->name('generate');
    Route::post('/campaigns/{campaign}/compose', Admin\CompositionPostController::class)->name('compose');
    Route::post('/campaigns/{campaign}/assets', Admin\AssetPostController::class)->name('assets.create');
    Route::get('/campaigns/{campaign}/assets/{asset}', Admin\AssetGetController::class)->name('assets.show');
    Route::get('/tasks/{task}', Admin\TaskGetController::class)->name('tasks.show');
    Route::post('/tasks/{task}/resume', Admin\TaskResumePostController::class)->name('tasks.resume');
    Route::post('/tasks/{task}/cancel', Admin\TaskCancelPostController::class)->name('tasks.cancel');
    Route::post('/tasks/{task}/reconcile', Admin\TaskReconcilePostController::class)->name('tasks.reconcile');
    Route::get('/revisions/{revision}', Admin\PackageGetController::class)->name('revisions.show');
    Route::get('/revisions/{revision}/artifacts/{position}', Admin\ArtifactGetController::class)->name('artifacts.show');
    Route::get('/revisions/{revision}/export', Admin\ExportGetController::class)->name('export');
});
