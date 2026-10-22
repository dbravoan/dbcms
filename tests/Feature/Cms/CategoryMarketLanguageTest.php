<?php

declare(strict_types=1);

use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Shop\Models\ProductCategory;
use Tests\Support\BlogShopComposition;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class, BlogShopComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'us']);
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('blog category stores dynamic translations and market assignment', function (): void {
    $markets = Market::query()->where('is_active', true)->take(2)->pluck('id')->all();

    $this->actingAs($this->admin, 'web')
        ->post(route('admin.blog.categories.store'), [
            'translations' => [
                'es' => ['name' => 'Categoría Dinámica', 'slug' => 'categoria-dinamica'],
                'en' => ['name' => 'Dynamic Category', 'slug' => 'dynamic-category'],
            ],
            'market_ids' => $markets,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.blog.categories.index'));

    $category = Category::query()
        ->whereHas('translations', fn ($query) => $query->where('slug', 'categoria-dinamica'))
        ->firstOrFail();

    expect($category->translations)->toHaveCount(2);
    expect($category->markets->pluck('id')->all())->toEqualCanonicalizing($markets);
});

test('shop category stores dynamic translations and market assignment', function (): void {
    $markets = Market::query()->where('is_active', true)->take(2)->pluck('id')->all();

    $this->actingAs($this->admin, 'web')
        ->post(route('admin.shop.categories.store'), [
            'translations' => [
                'es' => ['name' => 'Catálogo Dinámico', 'slug' => 'catalogo-dinamico'],
                'en' => ['name' => 'Dynamic Catalog', 'slug' => 'dynamic-catalog'],
            ],
            'market_ids' => $markets,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.shop.categories.index'));

    $category = ProductCategory::query()
        ->whereHas('translations', fn ($query) => $query->where('slug', 'catalogo-dinamico'))
        ->firstOrFail();

    expect($category->translations)->toHaveCount(2);
    expect($category->markets->pluck('id')->all())->toEqualCanonicalizing($markets);
});

test('category modals expose dynamic language tabs and market pickers', function (): void {
    $blog = $this->actingAs($this->admin, 'web')->get(route('admin.blog.categories.index'));
    $blog->assertOk();
    $blog->assertSee('cms-locale-switched', false);
    $blog->assertSee('market_ids[]', false);
    $blog->assertSee('searchSelect(', false);

    $shop = $this->actingAs($this->admin, 'web')->get(route('admin.shop.categories.index'));
    $shop->assertOk();
    $shop->assertSee('cms-locale-switched', false);
    $shop->assertSee('market_ids[]', false);
});
