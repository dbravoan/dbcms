<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Install\BrandRegistry;
use Tests\TestCase;

class BrandRegistryTest extends TestCase
{
    public function test_it_discovers_on_disk_brands_and_front_themes(): void
    {
        $registry = app(BrandRegistry::class);

        expect($registry->brandIdentifiers())
            ->toContain('dbcms')
            ->toContain('termocms');

        $termo = $registry->find('termocms');
        expect($termo)->not->toBeNull();
        expect($termo['name'])->toBe('Termosalud');
        expect($termo['accent'])->toBe('#0090A8');
        expect($termo['mode'])->toBe('light');
        expect($termo['edition'])->toBe('Corporate');
        expect((string) $termo['logo'])->toContain('termosalud-logo.png');

        $dbcms = $registry->find('dbcms');
        expect($dbcms)->not->toBeNull();
        expect($dbcms['name'])->toBe('dbCMS');
        expect($dbcms['accent'])->toBe('#00FF99');
        expect($dbcms['logo'])->toBeNull();

        expect($registry->frontThemeIdentifiers())->toContain('db-cyber');
    }

    public function test_it_exposes_a_neutral_fallback_and_rejects_unknown_brands(): void
    {
        $registry = app(BrandRegistry::class);

        $neutral = $registry->find(BrandRegistry::NEUTRAL_IDENTIFIER);
        expect($neutral)->not->toBeNull();
        expect($neutral['identifier'])->toBe('neutral');
        expect($neutral['accent'])->toBe('#38BDF8');
        expect($neutral['logo'])->toBeNull();

        expect($registry->find('not-a-real-brand'))->toBeNull();
    }
}
