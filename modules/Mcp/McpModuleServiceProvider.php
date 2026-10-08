<?php

declare(strict_types=1);

namespace Modules\Mcp;

use App\Core\Modules\BaseModuleServiceProvider;
use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Support\Facades\Route;
use Modules\Mcp\Resources\McpResourceRegistry;
use Modules\Mcp\Services\EntityCrudService;
use Modules\Mcp\Services\McpAccessPolicy;
use Modules\Mcp\Services\McpActionService;
use Modules\Mcp\Services\McpAuditLogger;
use Modules\Mcp\Services\McpServerService;
use Modules\Mcp\Services\McpToolDispatcher;
use Modules\Mcp\Services\ResourceCrudService;
use Modules\Mcp\Tools\McpToolRegistry;

class McpModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'mcp';

    protected string $name = 'Servidor MCP (Model Context Protocol)';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(McpServerService::class);
        $this->app->singleton(McpAccessPolicy::class);
        $this->app->singleton(McpToolRegistry::class);
        $this->app->singleton(McpResourceRegistry::class);
        $this->app->singleton(EntityCrudService::class);
        $this->app->singleton(ResourceCrudService::class);
        $this->app->singleton(McpActionService::class);
        $this->app->singleton(McpAuditLogger::class);
        $this->app->singleton(McpToolDispatcher::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'mcp');

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::prefix('api/mcp')
            ->middleware(['api', 'module.enabled:mcp'])
            ->group(__DIR__.'/Routes/api.php');

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
