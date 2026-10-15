<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Events\ModuleAvailabilityChanged;
use App\Models\CmsModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

test('default active manifests are opt in and rediscovery preserves administrator choices', function (): void {
    File::shouldReceive('exists')->with(base_path('modules'))->andReturnTrue();
    File::shouldReceive('directories')->with(base_path('modules'))->andReturn(['/virtual/Optional', '/virtual/Legacy']);
    foreach (['Optional' => ['identifier' => 'guide-opt-in', 'default_active' => false], 'Legacy' => ['identifier' => 'guide-legacy']] as $directory => $data) {
        File::shouldReceive('exists')->with('/virtual/'.$directory.'/module.json')->andReturnTrue();
        File::shouldReceive('get')->with('/virtual/'.$directory.'/module.json')->andReturn(json_encode($data + ['provider' => 'Modules\\'.$directory.'\\Provider'], JSON_THROW_ON_ERROR));
    }
    app(ModuleManager::class)->discoverModules();
    expect(CmsModule::where('identifier', 'guide-opt-in')->value('is_active'))->toBeFalse();
    expect(CmsModule::where('identifier', 'guide-legacy')->value('is_active'))->toBeTrue();
    CmsModule::where('identifier', 'guide-opt-in')->update(['is_active' => true]);
    app(ModuleManager::class)->discoverModules();
    expect(CmsModule::where('identifier', 'guide-opt-in')->value('is_active'))->toBeTrue();
});

test('availability notifications run after commit and discarded transactions emit none', function (): void {
    $manager = app(ModuleManager::class);
    $manager->discoverModules();
    Event::fake([ModuleAvailabilityChanged::class]);
    DB::transaction(function () use ($manager): void {
        $manager->deactivate('blog');
        Event::assertNotDispatched(ModuleAvailabilityChanged::class);
    });
    Event::assertDispatched(ModuleAvailabilityChanged::class, fn (ModuleAvailabilityChanged $event): bool => $event->module === 'blog' && ! $event->available && $event->operation === 'deactivate');
    Event::fake([ModuleAvailabilityChanged::class]);
    expect(fn () => DB::transaction(function () use ($manager): void {
        $manager->activate('blog');
        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);
    Event::assertNotDispatched(ModuleAvailabilityChanged::class);
});

test('uninstall preserves records while emitting module availability without module-specific imports', function (): void {
    $manager = app(ModuleManager::class);
    $manager->discoverModules();
    Event::fake([ModuleAvailabilityChanged::class]);
    $manager->uninstall('blog');
    expect(CmsModule::where('identifier', 'blog')->exists())->toBeTrue()->and($manager->isModuleEnabled('blog'))->toBeFalse();
    Event::assertDispatched(ModuleAvailabilityChanged::class, fn (ModuleAvailabilityChanged $event): bool => $event->operation === 'uninstall' && ! $event->available);
});

test('installing an opt-in module requires a separate explicit activation and preserves that decision on updates', function (): void {
    $manager = app(ModuleManager::class);
    $manager->discoverModules();
    foreach (['creative-studio', 'social-publishing'] as $identifier) {
        expect($manager->isModuleEnabled($identifier))->toBeFalse();
        $manager->install($identifier);
        expect($manager->isModuleEnabled($identifier))->toBeFalse();
        $manager->activate($identifier);
        $manager->install($identifier);
        expect($manager->isModuleEnabled($identifier))->toBeTrue();
        $manager->uninstall($identifier);
        $manager->install($identifier);
        expect($manager->isModuleEnabled($identifier))->toBeFalse();
    }
});
