<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Modules\ModuleManager;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Cms\CookieConsentService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Modules\Analytics\AnalyticsModuleServiceProvider;
use Modules\Analytics\Services\AnalyticsManagerService;
use Modules\Analytics\Services\Ga4CspContributor;
use PHPUnit\Framework\TestCase;

final class TrackingAvailabilityTest extends TestCase
{
    public function test_cookie_categories_follow_the_canonical_enable_switch(): void
    {
        $modules = $this->createMock(ModuleManager::class);
        $modules->method('isModuleEnabled')->willReturnMap([['shop', false], ['analytics', false], ['ads', false]]);
        $service = new CookieConsentService($modules);
        self::assertFalse($service->isModuleActive('analytics'));
        self::assertSame(['necessary'], array_keys($service->getDisclosedCategories('es')));

        $enabled = $this->createMock(ModuleManager::class);
        $enabled->method('isModuleEnabled')->willReturn(true);
        self::assertTrue((new CookieConsentService($enabled))->isModuleActive('analytics'));
    }

    public function test_tracking_csp_is_empty_when_owner_disabled_or_unconfigured(): void
    {
        $modules = $this->createMock(ModuleManager::class);
        $modules->method('isModuleEnabled')->willReturn(false);
        $analytics = $this->createMock(AnalyticsManagerService::class);
        $analytics->expects(self::never())->method('measurementId');
        self::assertSame([], (new Ga4CspContributor($modules, $analytics))->connectOrigins());

        $enabled = $this->createMock(ModuleManager::class);
        $enabled->method('isModuleEnabled')->willReturn(true);
        $unconfigured = $this->createMock(AnalyticsManagerService::class);
        $unconfigured->method('measurementId')->willReturn(null);
        self::assertSame([], (new Ga4CspContributor($enabled, $unconfigured))->connectOrigins());
    }

    public function test_configured_tracking_has_only_exact_collect_and_loader_origins(): void
    {
        $modules = $this->createMock(ModuleManager::class);
        $modules->method('isModuleEnabled')->willReturn(true);
        $analytics = $this->createMock(AnalyticsManagerService::class);
        $analytics->method('measurementId')->willReturn('G-ABC1234567');
        $contributor = new Ga4CspContributor($modules, $analytics);
        $origins = $contributor->connectOrigins();
        self::assertContains('https://www.google-analytics.com', $origins);
        self::assertContains('https://region1.google-analytics.com', $origins);
        self::assertContains('https://www.googletagmanager.com', $origins);
        self::assertStringNotContainsString('*', implode(' ', $origins));
        self::assertNotContains('https://www.facebook.com', $origins);

        $previous = Container::getInstance();
        $app = new Application(dirname(__DIR__, 3));
        $app->instance('env', 'testing');
        $app->instance('config', new Repository(['app' => ['env' => 'testing']]));
        (new AnalyticsModuleServiceProvider($app))->register();
        $app->instance(ModuleManager::class, $modules);
        $app->instance(AnalyticsManagerService::class, $analytics);
        Container::setInstance($app);
        try {
            $policy = (new \ReflectionMethod(SecurityHeaders::class, 'contentSecurityPolicy'))->invoke($app->make(SecurityHeaders::class), 'test-nonce');
            self::assertIsString($policy);
            self::assertStringContainsString("connect-src 'self' https://www.googletagmanager.com", $policy);
            self::assertStringNotContainsString('https://*', $policy);
        } finally {
            Container::setInstance($previous);
        }
    }
}
