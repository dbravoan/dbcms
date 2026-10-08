<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCmsIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for installer routes and static assets
        if ($request->is('install*') || $request->is('_debugbar*') || $request->is('up')) {
            return $next($request);
        }

        // Check if lock file exists
        $isInstalled = file_exists(storage_path('installed'));
        $shouldEnforce = ! app()->environment('testing') || config('cms.testing_enforce_installed_middleware', false);

        if (! $isInstalled && $shouldEnforce) {
            return redirect()->route('install.step1');
        }

        return $next($request);
    }
}
