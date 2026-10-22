<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\CmsModule;
use Tests\Support\AnalyticsComposition;
use Tests\Support\CmsFixtures;

uses(AnalyticsComposition::class);

test('every distributed theme emits configured analytics only while its owner is enabled', function (string $theme): void {
    $this->withoutVite();
    CmsFixtures::localization(['es'], ['es']);
    CmsFixtures::home(['es'], ['es']);
    app(ModuleSettingsServiceInterface::class)->set('analytics', 'ga4_measurement_id', 'G-CONTRACT123');
    config(['cms.theme' => $theme, 'cms.page_cache.enabled' => false]);
    $this->get('/es/es')->assertOk()->assertSee('G-CONTRACT123')->assertSee('cookie-consent-updated');
    CmsModule::where('identifier', 'analytics')->update(['is_active' => false]);
    $this->get('/es/es')->assertOk()->assertDontSee('G-CONTRACT123');
})->with(['default-clean', 'db-cyber', 'db-brutalist', 'db-minimal', 'db-sunset', 'db-editorial', 'db-enterprise']);
