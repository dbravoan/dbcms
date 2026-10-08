<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;
use Modules\Maintenance\Http\Controllers\MaintenanceIndexGetController;
use Modules\Maintenance\Http\Controllers\MaintenancePreviewGetController;
use Modules\Maintenance\Http\Controllers\MaintenanceSavePostController;
use Modules\Maintenance\Http\Controllers\MaintenanceTogglePostController;

Route::middleware([CheckPermission::class.':manage:modules'])->prefix('maintenance')->name('maintenance.')->group(function (): void {
    Route::get('/', MaintenanceIndexGetController::class)->name('index');
    Route::post('/save', MaintenanceSavePostController::class)->name('save');
    Route::post('/toggle', MaintenanceTogglePostController::class)->name('toggle');
    Route::get('/preview', MaintenancePreviewGetController::class)->name('preview');
});
