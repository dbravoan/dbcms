<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\ExecutionServiceInterface;
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
use App\Services\Capabilities\CmsCapabilityPresentation;
use App\Services\Capabilities\DiscoveryCapabilities;
use App\Services\Capabilities\EntityMutationService;
use App\Services\Capabilities\EntityReadService;
use App\Services\Capabilities\PlatformResourceCatalog;
use App\Services\Capabilities\ResourceCrudService;
use App\Services\Orchestration\ExecutionService;
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
        $this->app->singleton(EntityMutationService::class);
        $this->app->singleton(EntityReadService::class);
        $this->app->singleton(ResourceCrudService::class);
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
            foreach ($this->app->make(CmsActionCatalog::class)->all() as $tool) {
                $effect = match ($tool->name()) {
                    'cms_content_sections_sync', 'cms_notifications_mark_all_read', 'cms_media_upload', 'cms_cache_purge', 'cms_seo_filter_upsert' => 'draft',
                    default => $tool->destructive() ? 'delete' : ($tool->mutating() ? 'external' : 'read'),
                };
                $registry->register($tool, effect: $effect);
            }
            $this->app->make(DiscoveryCapabilities::class)->register($registry);
        });
    }
}
