<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Support\Facades\Route;

test('all registered static backoffice get surfaces respond coherently in both themes', function (string $theme): void {
    $this->seed(PlatformShowcaseSeeder::class);
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $user = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail();
    $this->actingAs($user);
    $evidence = [];
    foreach (Route::getRoutes() as $route) {
        $name = (string) $route->getName();
        if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }
        $response = in_array($name, ['admin.api.entity-search', 'admin.ai_assistant.providers.models'], true)
            ? $this->getJson('/'.$route->uri())
            : $this->get('/'.$route->uri());
        $status = $response->getStatusCode();
        $redirectTarget = match (true) {
            $name === 'admin.login', str_ends_with($route->uri(), '/dashboard') => route('admin.dashboard'),
            str_ends_with($route->uri(), '/seo') => route('admin.seo.audit'),
            $name === 'admin.maintenance.index' => route('admin.modules.settings', ['identifier' => 'maintenance']),
            $name === 'admin.shop.settings' => route('admin.modules.settings', ['identifier' => 'shop']),
            default => null,
        };
        $expected = match (true) {
            // These endpoints require explicit query arguments or an enabled feature.
            in_array($name, ['admin.api.entity-search', 'admin.ai_assistant.providers.models'], true) => [422],
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
    expect(count($evidence))->toBeGreaterThan(35);
    if (getenv('CMS_FINAL_AUDIT_EVIDENCE') === '1') {
        $directory = storage_path('app/private/final-system-audit');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/backoffice-'.$theme.'.json', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
})->with(['dbcms', 'termocms']);

test('every discovered module settings screen renders in both themes', function (string $theme): void {
    $this->seed(PlatformShowcaseSeeder::class);
    app(ModuleManager::class)->discoverModules();
    // This fixture deliberately activates every module, including opt-in Captcha.
    CmsModule::query()->update(['is_active' => true, 'is_installed' => true]);
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $user = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail();
    $this->actingAs($user);
    $manifests = glob(base_path('modules/*/module.json')) ?: [];
    expect(count($manifests))->toBeGreaterThanOrEqual(16);
    foreach ($manifests as $path) {
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $identifier = $manifest['identifier'];
        $response = $this->get(route('admin.modules.settings', ['identifier' => $identifier]));
        expect($response->getStatusCode())->toBe(200, "{$theme}: module {$identifier} settings must render.");
        $response->assertSee('data-admin-shell', false);
    }
})->with(['dbcms', 'termocms']);
