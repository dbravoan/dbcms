<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Orchestration;
use Illuminate\Support\Facades\Route;
use Modules\Orchestration\Http\Controllers;

Route::get('/catalog', Controllers\CatalogGetController::class)->name('catalog');
Route::post('/', Controllers\StartPostController::class)->name('start');
Route::get('/{execution}', Orchestration\ShowGetController::class)->name('show');
Route::post('/{execution}/respond', Controllers\RespondPostController::class)->name('respond');
Route::post('/{execution}/resume', Orchestration\ResumePostController::class)->name('resume');
Route::post('/{execution}/cancel', Orchestration\CancelPostController::class)->name('cancel');
