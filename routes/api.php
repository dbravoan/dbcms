<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthLoginPostController;
use App\Http\Controllers\Api\V1\AuthLogoutPostController;
use App\Http\Controllers\Api\V1\AuthRegisterPostController;
use App\Http\Controllers\Api\V1\SiteSchemaGetController;
use App\Http\Middleware\ThrottleApiAuthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Authentication Routes
    Route::post('/auth/register', AuthRegisterPostController::class)->middleware(ThrottleApiAuthentication::class.':register');
    Route::post('/auth/login', AuthLoginPostController::class)->middleware(ThrottleApiAuthentication::class.':login');

    // Public GEO & SEO Metadata
    Route::get('/geo/metadata', SiteSchemaGetController::class);

    // Protected OAuth2 Routes
    Route::middleware('auth:api')->group(function (): void {
        Route::post('/auth/logout', AuthLogoutPostController::class);
    });
});

// The optional Mcp module registers the standard endpoint at /mcp/cms.
