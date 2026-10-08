<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Services\Capabilities\LegacyCmsService;
use App\Services\Cms\CookieConsentService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class LegacyModuleDiscoverySecurityTest extends TestCase
{
    private ?ConnectionResolverInterface $previousResolver;

    protected function setUp(): void
    {
        $this->previousResolver = Model::getConnectionResolver();
        $database = new Manager;
        $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $database->bootEloquent();
        $database->getConnection()->getSchemaBuilder()->create('cms_modules', static function (Blueprint $table): void {
            $table->id();
            foreach (['identifier', 'name', 'version', 'description', 'provider', 'settings'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['is_active', 'is_core', 'is_pageable', 'is_installed'] as $field) {
                $table->boolean($field)->default(false);
            }
            $table->timestamps();
        });
        CmsModule::create(['identifier' => 'ai_assistant', 'name' => 'Assistant', 'version' => '1.0.0', 'is_active' => true, 'settings' => ['openai_api_key' => 'FAKE-PLAINTEXT-CREDENTIAL', 'other_private' => 'FAKE-ENCRYPTED-CREDENTIAL']]);
    }

    protected function tearDown(): void
    {
        if ($this->previousResolver !== null) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
    }

    public function test_legacy_module_discovery_returns_only_public_metadata(): void
    {
        $service = (new ReflectionClass(LegacyCmsService::class))->newInstanceWithoutConstructor();
        $result = $service->execute('cms_list_modules', []);
        self::assertCount(1, $result);
        self::assertSame('ai_assistant', $result[0]['identifier']);
        self::assertTrue($result[0]['is_active']);
        self::assertArrayNotHasKey('settings', $result[0]);
        self::assertStringNotContainsString('FAKE-', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_cookie_disclosure_respects_real_module_settings_and_default_availability(): void
    {
        $record = CmsModule::create(['identifier' => 'analytics', 'name' => 'Analytics', 'is_active' => true, 'is_installed' => true, 'settings' => []]);
        $service = new CookieConsentService(new ModuleManager($this->createMock(Application::class)));
        self::assertTrue($service->isModuleActive('analytics'));
        foreach ([false, 0, '0', 'false'] as $disabled) {
            $record->update(['settings' => ['is_enabled' => $disabled]]);
            self::assertFalse($service->isModuleActive('analytics'));
        }
        $record->update(['settings' => ['is_enabled' => true]]);
        self::assertTrue($service->isModuleActive('analytics'));
        $record->update(['is_active' => false]);
        self::assertFalse($service->isModuleActive('analytics'));
        $record->update(['is_active' => true, 'is_installed' => false]);
        self::assertFalse($service->isModuleActive('analytics'));
    }
}
