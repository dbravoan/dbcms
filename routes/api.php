<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GeoSeoController;
use App\Http\Middleware\AuthenticateMcpPassport;
use App\Http\Middleware\ThrottleApiAuthentication;
use Illuminate\Support\Facades\Route;
use Modules\AiAssistant\Http\Middleware\EnsureAiAuthorized;

Route::prefix('v1')->group(function (): void {
    // Authentication Routes
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware(ThrottleApiAuthentication::class.':register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware(ThrottleApiAuthentication::class.':login');

    // Public GEO & SEO Metadata
    Route::get('/geo/metadata', [GeoSeoController::class, 'siteSchema']);

    // Protected OAuth2 Routes
    Route::middleware('auth:api')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });
});

// Model Context Protocol (MCP) endpoints are registered by the Mcp module
// (modules/Mcp/Routes/api.php) under the api/mcp prefix.

Route::prefix('orchestration')->name('api.orchestration.')->middleware([AuthenticateMcpPassport::class.':ai:use', EnsureAiAuthorized::class.':use:ai'])->group(__DIR__.'/orchestration.php');
