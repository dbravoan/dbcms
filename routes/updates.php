<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Updates;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\PrivateUpdateResponse;
use App\Http\Middleware\SetBackofficeLocale;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('cms.admin_path', 'admin').'/updates')->name('admin.updates.')->middleware(['web', PrivateUpdateResponse::class, SetBackofficeLocale::class, EnsureAdminAuthenticated::class, CheckPermission::class.':manage:updates'])->group(function (): void {
    Route::get('/', Updates\UpdatesGetController::class)->name('index');
    Route::post('/check', Updates\UpdateCheckPostController::class)->middleware('throttle:6,1')->name('check');
    Route::post('/prepare', Updates\UpdatePreparePostController::class)->middleware('throttle:6,1')->name('prepare');
    Route::post('/adopt', Updates\UpdateAdoptPostController::class)->middleware('throttle:6,1')->name('adopt');
    Route::post('/confirm-password', Updates\UpdatePasswordPostController::class)->middleware('throttle:5,1')->name('confirm-password');
    Route::get('/runs/{run}', Updates\UpdateRunGetController::class)->whereUuid('run')->name('status');
    Route::get('/runs/{run}/proposal', Updates\UpdateProposalGetController::class)->whereUuid('run')->name('proposal');
    Route::post('/runs/{run}/approve', Updates\UpdateApprovePostController::class)->whereUuid('run')->name('approve');
    Route::post('/runs/{run}/advance', Updates\UpdateAdvancePostController::class)->whereUuid('run')->name('advance');
    Route::post('/runs/{run}/cancel', Updates\UpdateCancelPostController::class)->whereUuid('run')->name('cancel');
});
