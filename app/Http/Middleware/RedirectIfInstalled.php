<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $isInstalled = file_exists(storage_path('installed'));
        $shouldEnforce = ! app()->environment('testing') || config('cms.testing_enforce_installed_middleware', false);

        if ($isInstalled && $shouldEnforce) {
            // Allow the completion screen right after a successful install.
            if ($request->routeIs('install.step5') && $request->session()->get('install.just_installed') === true) {
                return $next($request);
            }

            $adminPath = config('cms.admin_path', 'admin');

            return redirect("/{$adminPath}/login");
        }

        return $next($request);
    }
}
