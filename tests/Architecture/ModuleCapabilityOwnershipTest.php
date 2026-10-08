<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\AvailableCapabilities;
use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Contracts\CapabilityPresentationInterface;
use App\Core\Modules\ModuleManager;
use App\Services\Capabilities\CmsActionCatalog;
use App\Services\Capabilities\CmsActionService;
use Modules\AiAssistant\Services\AiCapabilityRegistrar;
use Modules\ContentAudit\Capabilities\EditorialActionCatalog;
use Modules\Maintenance\Capabilities\MaintenanceActionCatalog;
use Modules\Search\Capabilities\SearchActionCatalog;

test('optional module actions do not belong to the always available core action catalog', function (): void {
    $service = Mockery::mock(CmsActionService::class);
    $names = array_map(static fn ($tool): string => $tool->name(), CmsActionCatalog::all($service));
    foreach (['cms_maintenance_get', 'cms_maintenance_toggle', 'cms_search_reindex', 'cms_search_stats', 'cms_audit_approve', 'cms_audit_reject', 'cms_audit_rollback'] as $name) {
        expect($names)->not->toContain($name);
    }
});

test('module catalogs declare their owners and the actual effects of every action', function (): void {
    $service = Mockery::mock(CmsActionService::class);
    $registry = new CapabilityRegistry;
    MaintenanceActionCatalog::register($registry, $service);
    SearchActionCatalog::register($registry, $service);
    EditorialActionCatalog::register($registry, $service);
    foreach ([
        'cms_maintenance_get' => ['maintenance', 'read'],
        'cms_maintenance_toggle' => ['maintenance', 'external'],
        'cms_search_stats' => ['search', 'read'],
        'cms_search_reindex' => ['search', 'draft'],
        'cms_audit_approve' => ['content_audit', 'publish'],
        'cms_audit_rollback' => ['content_audit', 'publish'],
        'cms_audit_reject' => ['content_audit', 'publish'],
    ] as $name => [$owner, $effect]) {
        expect($registry->metadata($name)['module'])->toBe($owner);
        expect($registry->metadata($name)['effect'])->toBe($effect);
    }
    $service->shouldReceive('maintenanceGet')->once()->andReturn(['enabled' => false]);
    expect($registry->get('cms_maintenance_get')->handle([]))->toBe(['enabled' => false]);
});

test('disabling owners removes their actions from discovery and refuses direct execution availability', function (): void {
    $registry = new CapabilityRegistry;
    $service = Mockery::mock(CmsActionService::class);
    MaintenanceActionCatalog::register($registry, $service);
    SearchActionCatalog::register($registry, $service);
    EditorialActionCatalog::register($registry, $service);
    $enabled = false;
    $modules = Mockery::mock(ModuleManager::class);
    $modules->shouldReceive('isModuleEnabled')->andReturnUsing(function () use (&$enabled): bool {
        return $enabled;
    });
    $authorizer = Mockery::mock(CapabilityAuthorizer::class);
    $authorizer->shouldReceive('allowed')->andReturnTrue();
    $presentation = Mockery::mock(CapabilityPresentationInterface::class);
    $presentation->shouldReceive('reason')->andReturnNull();
    $presentation->shouldReceive('schema')->andReturnUsing(static fn (string $name, array $entry): array => $entry);
    $available = new AvailableCapabilities($registry, $authorizer, $modules, $presentation);
    $actor = new ActorContext(1);
    expect($available->manifest($actor))->toBe([]);
    foreach ($registry->names() as $name) {
        expect($available->reason($name, $actor))->toBe('Módulo no disponible.');
    }
    $enabled = true;
    expect($available->manifest($actor))->toHaveCount(7);
    $registry->register(new CallableCapability('dependency_probe', 'Dependency probe', [], [], null, 'read', static fn (): array => []), dependencies: ['model:text']);
    $manifest = array_column($available->manifest($actor), null, 'name');
    expect($manifest['dependency_probe']['dependencies'])->toBe(['model:text']);
});

test('AI capability metadata declares model modality dependencies', function (): void {
    $registry = new CapabilityRegistry;
    (new AiCapabilityRegistrar)->register($registry);
    expect($registry->metadata('cms_ai_generate_image')['dependencies'] ?? null)->toBe(['model:image']);
    expect($registry->metadata('cms_ai_generate_video')['dependencies'] ?? null)->toBe(['model:video']);
    expect($registry->metadata('cms_ai_draft_article')['dependencies'] ?? null)->toBe(['model:text']);
    expect($registry->metadata('cms_ai_orchestrate_content')['dependencies'] ?? null)->toBe(['model:text', 'model:image']);
    expect($registry->metadata('cms_ai_research_and_draft')['dependencies'] ?? null)->toBe([]);
});
