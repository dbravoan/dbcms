<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Local-only persona switcher for demos and development.
 *
 * Hardened: it requires BOTH `local` (or `testing`) environment AND the explicit
 * `cms.quick_login` flag, resolves only the declared demo accounts (never an
 * arbitrary fallback user) and reuses the admin surface as a normal login.
 */
final class QuickLoginGetController extends Controller
{
    /**
     * Demo personas → seeded emails. Only these are reachable.
     *
     * @var array<string, string>
     */
    private const PERSONAS = [
        'admin' => 'admin@db-cms.io',
        'editor' => 'editor@db-cms.io',
        'author' => 'author@db-cms.io',
        'marketing' => 'marketing@db-cms.io',
        'viewer' => 'viewer@db-cms.io',
    ];

    public function __construct(
        private readonly Application $app,
    ) {}

    public function __invoke(Request $request, string $role): RedirectResponse
    {
        if (! $this->isAllowed()) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'El acceso rápido de personas demo no está disponible en este entorno.',
            ]);
        }

        if (! array_key_exists($role, self::PERSONAS)) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Persona demo desconocida.',
            ]);
        }

        $user = User::where('email', self::PERSONAS[$role])->first();

        if (! $user) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Usuario demo no encontrado. Ejecuta el seeder de demostración.',
            ]);
        }

        $request->session()->regenerate();
        Auth::guard('web')->login($user, true);
        $request->session()->save();

        return redirect()->route('admin.dashboard');
    }

    /**
     * Quick login is limited to local/testing environments AND must be opted in.
     */
    private function isAllowed(): bool
    {
        return $this->app->environment(['local', 'testing'])
            && (bool) config('cms.quick_login', false);
    }
}
