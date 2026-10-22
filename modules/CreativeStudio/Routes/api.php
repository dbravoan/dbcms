<?php

declare(strict_types=1);
use App\Enums\OAuthScope;
use App\Http\Middleware\AuthenticateMcpPassport;
use Illuminate\Support\Facades\Route;
use Modules\CreativeStudio\Http\Controllers\Admin;

Route::middleware(AuthenticateMcpPassport::class.':'.OAuthScope::CREATIVE_READ->value)->group(function (): void {
    Route::get('/', Admin\CampaignsGetController::class)->name('index');
    Route::get('/sources', Admin\SourcesGetController::class)->name('sources');
    Route::get('/campaigns/{campaign}', Admin\CampaignGetController::class)->name('show');
    Route::get('/campaigns/{campaign}/assets/{asset}', Admin\AssetGetController::class)->name('assets.show');
    Route::get('/tasks/{task}', Admin\TaskGetController::class)->name('tasks.show');
    Route::get('/revisions/{revision}', Admin\PackageGetController::class)->name('revisions.show');
    Route::get('/revisions/{revision}/artifacts/{position}', Admin\ArtifactGetController::class)->name('artifacts.show');
    Route::get('/revisions/{revision}/export', Admin\ExportGetController::class)->name('export');
});
Route::middleware(AuthenticateMcpPassport::class.':'.OAuthScope::CREATIVE_WRITE->value)->group(function (): void {
    Route::post('/campaigns', Admin\CampaignPostController::class)->name('create');
    Route::put('/campaigns/{campaign}/brief', Admin\CampaignBriefPutController::class)->name('brief');
    Route::put('/campaigns/{campaign}/dates', Admin\CampaignDatesPutController::class)->name('dates');
    Route::put('/campaigns/{campaign}/budget', Admin\CampaignBudgetPutController::class)->name('budget');
    Route::put('/campaigns/{campaign}/brand', Admin\BrandPutController::class)->name('brand');
    Route::post('/campaigns/{campaign}/revisions', Admin\RevisionPostController::class)->name('revisions.create');
    Route::post('/campaigns/{campaign}/generate', Admin\GenerationPostController::class)->name('generate');
    Route::post('/campaigns/{campaign}/compose', Admin\CompositionPostController::class)->name('compose');
    Route::post('/campaigns/{campaign}/assets', Admin\AssetPostController::class)->name('assets.create');
    Route::post('/tasks/{task}/resume', Admin\TaskResumePostController::class)->name('tasks.resume');
    Route::post('/tasks/{task}/cancel', Admin\TaskCancelPostController::class)->name('tasks.cancel');
    Route::post('/tasks/{task}/reconcile', Admin\TaskReconcilePostController::class)->name('tasks.reconcile');
});
