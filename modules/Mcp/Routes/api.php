<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateMcpPassport;
use Illuminate\Support\Facades\Route;
use Modules\Mcp\Http\Controllers\Api\Message;
use Modules\Mcp\Http\Controllers\Api\PostController as HandlePostController;
use Modules\Mcp\Http\Controllers\Api\Sse;
use Modules\Mcp\Http\Controllers\Api\Tools;

Route::middleware([AuthenticateMcpPassport::class.':mcp:access'])->group(function (): void {
    Route::get('/tools', Tools\GetController::class)->name('api.mcp.tools');
    Route::post('/', HandlePostController::class)->name('api.mcp.handle');
    Route::get('/sse', Sse\GetController::class)->name('api.mcp.sse');
    Route::post('/message', Message\PostController::class)->name('api.mcp.message');
});
