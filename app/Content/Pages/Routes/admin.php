<?php

declare(strict_types=1);

use App\Content\Pages\Http\Controllers\Admin\PageCreateGetController;
use App\Content\Pages\Http\Controllers\Admin\PageDeleteController;
use App\Content\Pages\Http\Controllers\Admin\PageEditGetController;
use App\Content\Pages\Http\Controllers\Admin\PagePostController;
use App\Content\Pages\Http\Controllers\Admin\PagePutController;
use App\Content\Pages\Http\Controllers\Admin\PagesGetController;
use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;

Route::middleware([CheckPermission::class.':manage:pages'])->prefix('pages')->name('pages.')->group(function (): void {
    Route::get('/', PagesGetController::class)->name('index');
    Route::get('/create', PageCreateGetController::class)->name('create');
    Route::post('/', PagePostController::class)->name('store');
    Route::get('/{page}/edit', PageEditGetController::class)->name('edit');
    Route::put('/{page}', PagePutController::class)->name('update');
    Route::delete('/{page}', PageDeleteController::class)->name('destroy');
});
