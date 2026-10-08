<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check() || ! Auth::guard('web')->user()) {
            return redirect()->route('admin.login');
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        // A role is a grouping mechanism, never an implicit administrative grant.
        if ((bool) $user->is_service_account || (! $user->isAdmin() && ! $user->hasPermission('access:admin'))) {
            Auth::guard('web')->logout();

            if ($request->expectsJson()) {
                abort(403, 'Acceso administrativo no autorizado.');
            }

            return redirect()->route('admin.login')->withErrors([
                'email' => 'Acceso denegado: Su cuenta no posee permisos administrativos.',
            ]);
        }

        return $next($request);
    }
}
