<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;
use Modules\ContentAudit\Http\Controllers\Admin\ApprovePostController;
use Modules\ContentAudit\Http\Controllers\Admin\DiffGetController;
use Modules\ContentAudit\Http\Controllers\Admin\IndexGetController;
use Modules\ContentAudit\Http\Controllers\Admin\RejectPostController;
use Modules\ContentAudit\Http\Controllers\Admin\ReviewGetController;
use Modules\ContentAudit\Http\Controllers\Admin\RollbackPostController;
use Modules\ContentAudit\Http\Controllers\Admin\SettingsPostController;

Route::middleware([CheckPermission::class.':access:admin'])->prefix('audit')->name('audit.')->group(function (): void {
    Route::get('/', IndexGetController::class)->name('index');
    Route::get('/review/{type}/{id}', ReviewGetController::class)->name('review');
    Route::post('/approve/{type}/{id}', ApprovePostController::class)->name('approve');
    Route::post('/reject/{type}/{id}', RejectPostController::class)->name('reject');
    Route::get('/diff/{revision}', DiffGetController::class)->name('diff');
    Route::post('/rollback/{revision}', RollbackPostController::class)->name('rollback');
    Route::post('/settings', SettingsPostController::class)->middleware(CheckPermission::class.':manage:modules')->name('settings');
});
