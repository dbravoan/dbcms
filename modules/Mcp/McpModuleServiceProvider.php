<?php

declare(strict_types=1);

namespace Modules\Mcp;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Modules\ModuleSettingsExtensionRegistry;
use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Modules\Mcp\Services\McpToolDispatcher;

class McpModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'mcp';

    protected string $name = 'Servidor MCP (Model Context Protocol)';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(McpToolDispatcher::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleSettingsExtensionRegistry::class)->registerNormalizer('mcp', static function (array $settings): array {
            Validator::make($settings, ['rate_limit_per_minute' => ['sometimes', 'integer', 'min:1']])->validate();

            return $settings;
        });
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'mcp');

        RateLimiter::for('mcp', static function (Request $request): Limit {
            // Runs after Passport verification; never derive identity from payloads or unverified JWTs.
            $identity = $request->attributes->get('is_m2m')
                ? 'client:'.$request->attributes->get('oauth_client_id')
                : 'user:'.$request->user()?->getAuthIdentifier();
            $perMinute = max(1, (int) app(ModuleSettingsServiceInterface::class)->get('mcp', 'rate_limit_per_minute', 60));

            return Limit::perMinute($perMinute)->by('mcp:'.$identity);
        });
        $this->registerRoutes();
        $this->loadRoutesFrom(__DIR__.'/Routes/native.php');
    }

    protected function registerRoutes(): void
    {
        Route::prefix(trim((string) config('cms.admin_path', 'admin'), '/').'/modules/mcp')
            ->middleware(['web', 'auth', EnsureAdminAuthenticated::class, 'module.enabled:mcp'])
            ->group(__DIR__.'/Routes/web.php');
    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => __('admin.nav.items.mcp'),
                'route' => 'admin.modules.settings',
                'params' => ['identifier' => 'mcp'],
                'url' => route('admin.modules.settings', ['identifier' => 'mcp']),
                'icon' => 'network-wired',
                'permission' => 'manage:modules',
            ],
        ];
    }
}
