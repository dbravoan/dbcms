<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\CmsFixtures;
use Tests\Support\ReleaseSurfaceComposition;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, ReleaseSurfaceComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('all registered static backoffice get surfaces respond coherently in both themes', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $user = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail();
    $this->actingAs($user);
    $evidence = [];
    foreach (Route::getRoutes() as $route) {
        $name = (string) $route->getName();
        if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }
        $response = in_array($name, ['admin.api.entity-search', 'admin.ai_assistant.providers.models', 'admin.creative-studio.sources'], true)
            ? $this->getJson('/'.$route->uri())
            : $this->get('/'.$route->uri());
        $status = $response->getStatusCode();
        $redirectTarget = match (true) {
            $name === 'admin.login', str_ends_with($route->uri(), '/dashboard') => route('admin.dashboard'),
            str_ends_with($route->uri(), '/seo') => route('admin.seo.audit'),
            $name === 'admin.shop.settings' => route('admin.modules.settings', ['identifier' => 'shop']),
            default => null,
        };
        $expected = match (true) {
            // These endpoints require explicit query arguments or an enabled feature.
            in_array($name, ['admin.api.entity-search', 'admin.ai_assistant.providers.models', 'admin.creative-studio.sources'], true) => [422],
            in_array($name, ['admin.orchestration.catalog', 'admin.maintenance.preview'], true) => [200, 404],
            $redirectTarget !== null => [302],
            default => [200],
        };
        expect($status)->toBeIn($expected, "{$theme}: {$name} /{$route->uri()} returned {$status}.");
        if ($redirectTarget !== null) {
            $response->assertRedirect($redirectTarget);
        }
        $evidence[] = ['name' => $name, 'uri' => $route->uri(), 'status' => $status, 'expected' => $expected];
    }
    expect(array_column($evidence, 'name'))->toContain('admin.dashboard', 'admin.pages.index', 'admin.blog.posts.index');
    if (getenv('CMS_FINAL_AUDIT_EVIDENCE') === '1') {
        $directory = storage_path('app/private/final-system-audit');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/backoffice-'.$theme.'.json', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
})->with(['dbcms', 'termocms']);

test('every discovered module settings screen renders in both themes', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $user = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail();
    $this->actingAs($user);
    foreach (self::TEST_MODULES as $identifier) {
        $response = $this->get(route('admin.modules.settings', ['identifier' => $identifier]));
        expect($response->getStatusCode())->toBe(200, "{$theme}: module {$identifier} settings must render.");
        $response->assertSee('data-admin-shell', false);
    }
})->with(['dbcms', 'termocms']);
