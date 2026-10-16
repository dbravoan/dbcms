<?php

declare(strict_types=1);

namespace Modules\Mcp;

use App\Core\Modules\BaseModuleServiceProvider;
use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Support\Facades\Route;
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
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'mcp');

        $this->registerRoutes();
        $this->loadRoutesFrom(__DIR__.'/Routes/native.php');
    }

    protected function registerRoutes(): void
    {
        Route::prefix(trim((string) config('cms.admin_path', 'admin'), '/').'/modules/mcp')
            ->middleware(['web', 'auth', EnsureAdminAuthenticated::class, 'module.enabled:mcp'])
            ->group(__DIR__.'/Routes/web.php');
    }

    public function hasDedicatedPanel(): bool
    {
        return true;
    }

    public function getDedicatedPanelUrl(): ?string
    {
        return route('admin.modules.settings', ['identifier' => 'mcp']);
    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => 'Servidor MCP & Agentes',
                'route' => 'admin.modules.settings',
                'params' => ['identifier' => 'mcp'],
                'url' => route('admin.modules.settings', ['identifier' => 'mcp']),
                'icon' => 'network-wired',
                'permission' => 'manage:modules',
            ],
        ];
    }
}
