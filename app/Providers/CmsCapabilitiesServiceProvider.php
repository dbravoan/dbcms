<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\ExecutionServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\CapabilityExecutor;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Contracts\CapabilityAuditInterface;
use App\Core\Capabilities\Contracts\CapabilityExecutorInterface;
use App\Core\Capabilities\Contracts\CapabilityPresentationInterface;
use App\Core\Capabilities\ExecutionContext;
use App\Core\Capabilities\Resources\CapabilityResourceRegistry;
use App\Services\Capabilities\CapabilityAuditLogger;
use App\Services\Capabilities\CapabilityAuditService;
use App\Services\Capabilities\CmsActionCatalog;
use App\Services\Capabilities\CmsActionService;
use App\Services\Capabilities\CmsCapabilityPresentation;
use App\Services\Capabilities\EntityCrudService;
use App\Services\Capabilities\LegacyCmsService;
use App\Services\Capabilities\PlatformResourceCatalog;
use App\Services\Capabilities\ResourceCrudService;
use App\Services\Orchestration\ExecutionService;
use App\Services\Orchestration\OrchestrationCapabilities;
use Illuminate\Support\ServiceProvider;

/** Registers CMS domain adapters even if the MCP transport is disabled. */
final class CmsCapabilitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExecutionServiceInterface::class, ExecutionService::class);
        $this->app->bind(CapabilityExecutorInterface::class, CapabilityExecutor::class);
        $this->app->bind(CapabilityPresentationInterface::class, CmsCapabilityPresentation::class);
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->scoped(ExecutionContext::class);
        $this->app->singleton(CapabilityResourceRegistry::class);
        $this->app->singleton(EntityCrudService::class);
        $this->app->singleton(ResourceCrudService::class);
        $this->app->singleton(CmsActionService::class);
        $this->app->singleton(CapabilityAuditLogger::class);
        $this->app->bind(CapabilityAuditInterface::class, CapabilityAuditService::class);
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $resources = $this->app->make(CapabilityResourceRegistry::class);
            $resources->registerMany(PlatformResourceCatalog::all());
            $registry = $this->app->make(CapabilityRegistry::class);
            foreach (['EntityTypes', 'EntityList', 'EntityGet', 'EntityCreate', 'EntityUpdate', 'EntityDelete'] as $name) {
                $registry->register($this->app->make('App\\Core\\Capabilities\\Tools\\Entity\\'.$name.'Tool'));
            }
            foreach (['ResourceTypes', 'ResourceList', 'ResourceGet', 'ResourceCreate', 'ResourceUpdate', 'ResourceDelete'] as $name) {
                $registry->register($this->app->make('App\\Core\\Capabilities\\Tools\\Resource\\'.$name.'Tool'));
            }
            foreach (CmsActionCatalog::all($this->app->make(CmsActionService::class)) as $tool) {
                $effect = match ($tool->name()) {
                    'cms_content_sections_sync', 'cms_notifications_mark_all_read', 'cms_media_upload', 'cms_cache_purge', 'cms_seo_filter_upsert' => 'draft',
                    default => $tool->destructive() ? 'delete' : ($tool->mutating() ? 'external' : 'read'),
                };
                $registry->register($tool, effect: $effect);
            }
            $this->registerLegacy($registry);
            $this->app->make(OrchestrationCapabilities::class)->register($registry);
        });
    }

    private function registerLegacy(CapabilityRegistry $registry): void
    {
        $config = json_decode((string) file_get_contents(resource_path('capabilities/legacy-tools.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ((array) ($config['tools'] ?? []) as $definition) {
            $name = (string) $definition['name'];
            if (str_starts_with($name, 'cms_ai_')) {
                continue;
            }
            $schema = (array) ($definition['inputSchema'] ?? $definition['parameters'] ?? []);
            $module = str_contains($name, 'post') || str_contains($name, 'categor') ? 'blog' : (str_contains($name, 'page') ? 'pages' : (str_contains($name, 'product') ? 'shop' : (str_contains($name, 'leads') ? 'forms' : null)));
            $permission = match ($module) {
                'blog' => 'manage:posts', 'pages' => 'manage:pages', 'shop' => 'manage:shop', 'forms' => 'manage:forms', default => 'access:admin'
            };
            $effect = str_starts_with($name, 'cms_publish_') ? 'publish' : (str_starts_with($name, 'cms_delete_') ? 'delete' : ((str_starts_with($name, 'cms_create_') || str_starts_with($name, 'cms_update_')) ? 'draft' : 'read'));
            $ability = $effect === 'delete' ? 'destructive' : ($effect === 'read' ? 'read' : 'write');
            $registry->register(new CallableCapability($name, (string) ($definition['description'] ?? ''), (array) ($schema['properties'] ?? []), array_values(array_map('strval', (array) ($schema['required'] ?? []))), $permission, $ability, function (array $args) use ($name): array {
                $result = $this->app->make(LegacyCmsService::class)->execute($name, $args);

                return is_array($result) ? $result : ['result' => $result];
            }), $module, $effect, function (ActorContext $actor) use ($name): ?string {
                if (in_array($name, ['cms_search_content', 'cms_get_entity', 'cms_research_topic_context', 'cms_browse_content', 'cms_search'], true)) {
                    $auth = $this->app->make(CapabilityAuthorizer::class);
                    foreach (['manage:posts', 'manage:pages', 'manage:shop'] as $permission) {
                        if (! $auth->allowed($actor, 'read', $permission)) {
                            return 'Permisos insuficientes para esta búsqueda transversal.';
                        }
                    }
                }

                return null;
            });
        }
    }
}
