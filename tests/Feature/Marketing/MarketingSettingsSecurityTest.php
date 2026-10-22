<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Services\Capabilities\CapabilityAuditLogger;
use App\Services\Capabilities\PlatformAdministrationActions;
use Tests\Support\AnalyticsComposition;

uses(AnalyticsComposition::class);

test('module schema secrets never enter capability responses or audit arguments even with opaque field names', function (): void {
    $settings = Mockery::mock(ModuleSettingsServiceInterface::class);
    $settings->shouldReceive('getSchema')->with('analytics')->andReturn([
        'opaque_blob' => ['type' => 'text', 'label' => 'Credencial', 'secret' => true],
        'ga4_service_account_json' => ['type' => 'textarea', 'label' => 'GA4', 'secret' => true],
        'ga4_property_id' => ['type' => 'text', 'label' => 'Propiedad'],
    ]);
    $settings->shouldReceive('setMany')->with('analytics', [])->once();
    $settings->shouldReceive('getAll')->with('analytics')->andReturn(['opaque_blob' => 'opaque-secret-fixture', 'ga4_service_account_json' => '{"private_key":"private-fixture"}', 'ga4_property_id' => '12345']);
    app()->instance(ModuleSettingsServiceInterface::class, $settings);
    $audit = app(CapabilityAuditLogger::class);
    $redacted = $audit->redact(['identifier' => 'analytics', 'settings' => ['opaque_blob' => 'opaque-secret-fixture', 'ga4_service_account_json' => '{"private_key":"private-fixture"}', 'ga4_property_id' => '12345']]);
    expect(json_encode($redacted))->not->toContain('opaque-secret-fixture')->not->toContain('private-fixture')->toContain('12345');
    app()->forgetInstance(PlatformAdministrationActions::class);
    $result = app(PlatformAdministrationActions::class)->moduleSettings('analytics', []);
    expect(json_encode($result))->not->toContain('opaque-secret-fixture')->not->toContain('private-fixture')->toContain('12345');
    expect($result['settings']['ga4_service_account_json'])->toBe('••••••••');
});
