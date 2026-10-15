<?php

declare(strict_types=1);

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnforceJsonResponse;
use App\Http\Middleware\EnsureCmsIsInstalled;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottlePublicWrites;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'cms_cookie_consent',
            'cms_geo_preference',
        ]);
        $middleware->validateCsrfTokens(except: [
            'oauth/*',
        ]);
        $middleware->web(append: [
            EnsureCmsIsInstalled::class,
            CheckMaintenanceMode::class,
            SecurityHeaders::class,
            ThrottlePublicWrites::class,
        ]);
        $middleware->api(prepend: [
            EnforceJsonResponse::class,
        ]);
        $middleware->alias([
            'module.enabled' => EnsureModuleEnabled::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation',
            'api_key', 'api_secret', 'api_key_secret', 'client_secret', 'access_token', 'refresh_token',
            'ga4_service_account_json', 'private_key',
            'credentials', 'secrets', 'settings', 'clientSecret', 'apiKey', 'apiKeySecret',
        ]);
    })->create();
