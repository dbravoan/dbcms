<?php

declare(strict_types=1);

use App\Contracts\Services\LocalizationServiceInterface;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\User;
use App\Services\Cms\ModuleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogComposition;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class, BlogComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

/**
 * The module settings contract: a `slug_{locale}` placeholder is expanded at
 * read time into one field per active language, so a language created in the
 * backoffice (French) is editable and persisted without touching code.
 */
function ensureFrenchLanguage(): Language
{
    return Language::firstOrCreate(['code' => 'fr'], [
        'name' => 'French',
        'native_name' => 'Français',
        'direction' => 'ltr',
        'is_active' => true,
    ]);
}

test('the schema expands the locale placeholder for every active language', function (): void {
    ensureFrenchLanguage();

    $schema = app(ModuleSettingsService::class)->getSchema('blog');

    expect($schema)->toHaveKey('slug_fr')
        ->toHaveKey('slug_es')
        ->and($schema['slug_fr']['scope'])->toBe('locale')
        ->and($schema['slug_fr']['locale'])->toBe('fr')
        ->and($schema['slug_fr']['label'])->toContain('Français');
});

test('a slug for a new language persists and resolves on the front', function (): void {
    ensureFrenchLanguage();

    $module = CmsModule::where('identifier', 'blog')->firstOrFail();
    $before = $module->settings;

    try {
        app(ModuleSettingsService::class)->setMany('blog', ['slug_es' => 'noticias', 'slug_fr' => 'nouvelles']);

        expect(module_setting('blog', 'slug_fr'))->toBe('nouvelles')
            ->and(app(LocalizationServiceInterface::class)->getModulePrefix('blog', 'fr'))->toBe('nouvelles');
    } finally {
        CmsModule::where('identifier', 'blog')->update(['settings' => $before]);
    }
});

test('the settings save controller persists the language slugs and redirects', function (): void {
    ensureFrenchLanguage();

    $admin = User::first();
    $module = CmsModule::where('identifier', 'blog')->firstOrFail();
    $before = $module->settings;

    try {
        $response = $this->actingAs($admin, 'web')
            ->put(route('admin.modules.settings.update', ['identifier' => 'blog']), [
                'slug_es' => 'noticias',
                'slug_fr' => 'nouvelles',
            ]);

        $response->assertRedirect(route('admin.modules.settings', ['identifier' => 'blog']));

        expect(module_setting('blog', 'slug_fr'))->toBe('nouvelles')
            ->and(app(LocalizationServiceInterface::class)->getModulePrefix('blog', 'fr'))->toBe('nouvelles');
    } finally {
        CmsModule::where('identifier', 'blog')->update(['settings' => $before]);
    }
});

test('a language deactivated later keeps its stored slug resolvable', function (): void {
    $fr = ensureFrenchLanguage();

    $module = CmsModule::where('identifier', 'blog')->firstOrFail();
    $before = $module->settings;

    try {
        app(ModuleSettingsService::class)->setMany('blog', ['slug_fr' => 'nouvelles']);
        $fr->update(['is_active' => false]);

        $localization = app(LocalizationServiceInterface::class);

        expect($localization->activeLocales())->not->toContain('fr')
            ->and($localization->getAllModuleSlugs('blog'))->toContain('nouvelles');
    } finally {
        $fr->update(['is_active' => true]);
        CmsModule::where('identifier', 'blog')->update(['settings' => $before]);
    }
});

test('the settings screen renders one tab per active language', function (): void {
    ensureFrenchLanguage();

    $admin = User::first();
    $module = CmsModule::where('identifier', 'blog')->firstOrFail();
    $before = $module->settings;

    try {
        $schema = app(ModuleSettingsService::class)->getSchema('blog');
        app(ModuleSettingsService::class)->setMany('blog', ['slug_fr' => 'nouvelles'] + array_map(
            static fn (array $field): string => (string) ($field['default'] ?? ''),
            array_intersect_key($schema, array_flip(['slug_es', 'slug_en']))
        ));

        $response = $this->actingAs($admin, 'web')
            ->get(route('admin.modules.settings', ['identifier' => 'blog']));

        $response->assertOk()
            ->assertSee('slug_fr', false)
            ->assertSee('slug_es', false)
            ->assertSee('Ajustes por idioma', false);
    } finally {
        CmsModule::where('identifier', 'blog')->update(['settings' => $before]);
    }
});
