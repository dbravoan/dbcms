<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\Admin\CategoriesGetController;
use Modules\Blog\Http\Controllers\Admin\CategoryDeleteController;
use Modules\Blog\Http\Controllers\Admin\CategoryPostController;
use Modules\Blog\Http\Controllers\Admin\CategoryPutController;
use Modules\Blog\Http\Controllers\Admin\PostCreateGetController;
use Modules\Blog\Http\Controllers\Admin\PostDeleteController;
use Modules\Blog\Http\Controllers\Admin\PostEditGetController;
use Modules\Blog\Http\Controllers\Admin\PostPostController;
use Modules\Blog\Http\Controllers\Admin\PostPutController;
use Modules\Blog\Http\Controllers\Admin\PostsGetController;

Route::middleware([CheckPermission::class.':manage:posts'])->prefix('blog')->name('blog.')->group(function (): void {
    // Posts CRUD
    Route::get('/posts', PostsGetController::class)->name('posts.index');
    Route::get('/posts/create', PostCreateGetController::class)->name('posts.create');
    Route::post('/posts', PostPostController::class)->name('posts.store');
    Route::get('/posts/{post}/edit', PostEditGetController::class)->name('posts.edit');
    Route::put('/posts/{post}', PostPutController::class)->name('posts.update');
    Route::delete('/posts/{post}', PostDeleteController::class)->name('posts.destroy');

    // Categories
    Route::get('/categories', CategoriesGetController::class)->name('categories.index');
    Route::post('/categories', CategoryPostController::class)->name('categories.store');
    Route::put('/categories/{category}', CategoryPutController::class)->name('categories.update');
    Route::delete('/categories/{category}', CategoryDeleteController::class)->name('categories.destroy');
});
