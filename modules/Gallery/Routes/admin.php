<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;
use Modules\Gallery\Http\Controllers\Admin\GalleriesGetController;
use Modules\Gallery\Http\Controllers\Admin\GalleryCreateGetController;
use Modules\Gallery\Http\Controllers\Admin\GalleryDeleteController;
use Modules\Gallery\Http\Controllers\Admin\GalleryEditGetController;
use Modules\Gallery\Http\Controllers\Admin\GalleryPostController;
use Modules\Gallery\Http\Controllers\Admin\GalleryPutController;

Route::middleware([CheckPermission::class.':manage:galleries'])->prefix('galleries')->name('galleries.')->group(function (): void {
    Route::get('/', GalleriesGetController::class)->name('index');
    Route::get('/create', GalleryCreateGetController::class)->name('create');
    Route::post('/', GalleryPostController::class)->name('store');
    Route::get('/{gallery}/edit', GalleryEditGetController::class)->name('edit');
    Route::put('/{gallery}', GalleryPutController::class)->name('update');
    Route::delete('/{gallery}', GalleryDeleteController::class)->name('destroy');
});
