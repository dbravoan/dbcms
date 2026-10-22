<?php

declare(strict_types=1);

use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\CategoryTranslation;
use Modules\Blog\Services\PostDomainService;
use Tests\Support\BlogComposition;

uses(RefreshDatabase::class, BlogComposition::class);

beforeEach(function (): void {
    $this->spanish = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $this->english = Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_default' => false, 'is_active' => true]);

    $this->marketEs = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->marketUs = Market::firstOrCreate(['code' => 'us'], ['name' => 'United States', 'default_locale' => 'en', 'is_default' => false, 'is_active' => true]);

    $this->marketEs->languages()->sync([$this->spanish->id => ['is_default' => true]]);
    $this->marketUs->languages()->sync([$this->english->id => ['is_default' => true]]);

    $this->user = User::create(['name' => 'Author User', 'email' => 'author@example.com', 'password' => bcrypt('secret')]);

    $this->category = Category::create(['is_active' => true]);
    CategoryTranslation::create(['category_id' => $this->category->id, 'locale' => 'es', 'name' => 'Tecnología', 'slug' => 'tecnologia']);
    CategoryTranslation::create(['category_id' => $this->category->id, 'locale' => 'en', 'name' => 'Technology', 'slug' => 'technology']);

    $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user->roles()->attach($admin->getKey());
    $this->actingAs($this->user);
    $this->postService = app(PostDomainService::class);
});

test('it serves a blog post natively on its localized market route without dummy CMS page', function (): void {
    $post = $this->postService->createPost([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'is_published' => true,
        'published_at' => now(),
        'market_ids' => [$this->marketEs->id],
        'translations' => [
            'es' => [
                'title' => 'Guía de Optimización GEO y SEO',
                'content' => '## Sección Principal\nContenido en español con alta densidad semántica.',
                'summary' => 'Resumen de optimización GEO.',
            ],
        ],
    ]);

    $response = $this->get('/es/es/blog/guia-de-optimizacion-geo-y-seo');

    $response->assertStatus(200)
        ->assertSee('Guía de Optimización GEO y SEO')
        ->assertSee('application/ld+json', false)
        ->assertSee('hreflang="es-ES"', false);
});

test('it returns 404 when accessing post from an unassigned market', function (): void {
    $this->postService->createPost([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'is_published' => true,
        'published_at' => now(),
        'market_ids' => [$this->marketEs->id], // Only assigned to ES
        'translations' => [
            'es' => ['title' => 'Post Solo para España', 'content' => 'Texto exclusivo para España.'],
        ],
    ]);

    $response = $this->get('/us/en/blog/post-solo-para-espana');

    $response->assertStatus(404);
});
