<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Services\BrandServiceInterface;
use App\DTOs\BrandIdentity;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BrandServiceTest extends TestCase
{
    private BrandServiceInterface $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = app(BrandServiceInterface::class);
    }

    public function test_it_resolves_the_active_theme_from_config(): void
    {
        Config::set('cms.admin_theme', 'termocms');

        expect($this->brand->activeTheme())->toBe('termocms')
            ->and($this->brand->current()->theme)->toBe('termocms');
    }

    public function test_it_reads_the_brand_from_the_theme_manifest(): void
    {
        Config::set('cms.admin_theme', 'termocms');

        $brand = $this->brand->current();

        expect($brand)->toBeInstanceOf(BrandIdentity::class);
        expect($brand->name)->toBe('Termosalud');
        expect($brand->tagline)->toBe('Plataforma Corporativa');
        expect($brand->edition)->toBe('Corporate');
        expect($brand->accent)->toBe('#0090A8');
        expect($brand->mode)->toBe('light');
        expect($brand->logo)->toBe('termosalud-logo.png');
        expect($brand->valid)->toBeTrue();
    }

    public function test_it_falls_back_to_dbcms_when_the_configured_theme_is_missing(): void
    {
        Config::set('cms.admin_theme', 'does-not-exist');

        expect($this->brand->activeTheme())->toBe('dbcms')
            ->and($this->brand->current()->theme)->toBe('dbcms');
    }

    public function test_environment_tagline_overrides_the_manifest(): void
    {
        Config::set('cms.admin_theme', 'dbcms');
        Config::set('cms.tagline', 'Custom Env Tagline');

        expect($this->brand->current()->tagline)->toBe('Custom Env Tagline');
    }

    public function test_dbcms_declares_no_edition_but_keeps_its_accent(): void
    {
        Config::set('cms.admin_theme', 'dbcms');
        Config::set('cms.tagline', null);

        $brand = $this->brand->current();

        expect($brand->edition)->toBeNull();
        expect($brand->accent)->toBe('#00FF99');
        expect($brand->mode)->toBe('dark');
        expect($brand->tagline)->toBe('Arquitectura Modular y Agéntica');
    }

    public function test_for_theme_resolves_any_theme_identity(): void
    {
        $brand = $this->brand->forTheme('termocms');

        expect($brand->theme)->toBe('termocms')
            ->and($brand->name)->toBe('Termosalud');

        $missing = $this->brand->forTheme('nope');
        expect($missing->valid)->toBeFalse()
            ->and($missing->edition)->toBeNull();
    }
}
