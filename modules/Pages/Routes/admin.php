<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;
use Modules\Pages\Http\Controllers\Admin\PageCreateGetController;
use Modules\Pages\Http\Controllers\Admin\PageDeleteController;
use Modules\Pages\Http\Controllers\Admin\PageEditGetController;
use Modules\Pages\Http\Controllers\Admin\PagePostController;
use Modules\Pages\Http\Controllers\Admin\PagePutController;
use Modules\Pages\Http\Controllers\Admin\PagesGetController;

Route::middleware([CheckPermission::class.':manage:pages'])->prefix('pages')->name('pages.')->group(function (): void {
    Route::get('/', PagesGetController::class)->name('index');
    Route::get('/create', PageCreateGetController::class)->name('create');
    Route::post('/', PagePostController::class)->name('store');
    Route::get('/{page}/edit', PageEditGetController::class)->name('edit');
    Route::put('/{page}', PagePutController::class)->name('update');
    Route::delete('/{page}', PageDeleteController::class)->name('destroy');
});
