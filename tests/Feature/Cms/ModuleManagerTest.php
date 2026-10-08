<?php

declare(strict_types=1);

use App\Core\Filters\FilterModuleRegistry;
use App\Core\Modules\ModuleManager;
use App\Core\Search\Contracts\SearchSourceRegistryInterface;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CmsFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CmsFoundationSeeder::class);
    $this->manager = app(ModuleManager::class);
    $this->user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user->roles()->sync([$adminRole->id]);
});

test('it discovers all modules present in modules folder', function (): void {
    $discovered = $this->manager->discoverModules();

    expect($discovered)->toHaveKey('blog')
        ->and($discovered)->toHaveKey('gallery')
        ->and($discovered)->toHaveKey('forms')
        ->and($discovered)->toHaveKey('pages');

    expect(CmsModule::where('identifier', 'blog')->exists())->toBeTrue();
});

test('it toggles module activation status cleanly', function (): void {
    $this->manager->discoverModules();

    $this->manager->deactivate('blog');
    expect(CmsModule::where('identifier', 'blog')->value('is_active'))->toBeFalse();

    $this->manager->activate('blog');
    expect(CmsModule::where('identifier', 'blog')->value('is_active'))->toBeTrue();
});

test('backoffice displays all registered modules in marketplace UI', function (): void {
    $this->manager->discoverModules();

    $response = $this->actingAs($this->user, 'web')->get('/admin/modules');

    $response->assertStatus(200)
        ->assertSee(__('admin.page_titles.modules'))
        ->assertSee('Blog Engine')
        ->assertSee('Dynamic Form Builder');
});

test('deactivating a module disables its admin routes', function (): void {
    $this->manager->discoverModules();

    // Active module route works
    $this->actingAs($this->user, 'web')->get('/admin/blog/posts')->assertStatus(200);

    // Deactivate module
    $this->manager->deactivate('blog');

    // Route returns 404 because module is disabled
    $this->actingAs($this->user, 'web')->get('/admin/blog/posts')->assertStatus(404);
});

test('uninstallation preserves domain data and cannot be reversed by activation', function (): void {
    $this->manager->discoverModules();
    $post = Post::create(['user_id' => $this->user->id, 'is_published' => false]);
    $this->manager->uninstall('blog');
    expect($this->manager->isActive('blog'))->toBeFalse()
        ->and($this->manager->activate('blog'))->toBeFalse()
        ->and($post->fresh())->not->toBeNull();
    $this->get('/es/es/blog')->assertNotFound();
});

test('disabled providers remain unavailable to filter and search consumers', function (): void {
    $this->manager->discoverModules();
    $filters = app(FilterModuleRegistry::class);
    $sources = app(SearchSourceRegistryInterface::class);
    expect($filters->get('shop'))->not->toBeNull()->and($sources->has('product'))->toBeTrue();
    $this->manager->deactivate('shop');
    expect($filters->get('shop'))->toBeNull()->and($sources->has('product'))->toBeFalse();
});
