<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('hierarchical category tree computes recursive slug paths correctly', function (): void {
    $catLlm = Category::whereHas('translations', function ($q): void {
        $q->where('slug', 'modelos-llm')->where('locale', 'es');
    })->first();

    expect($catLlm)->not->toBeNull();
    $slugPathEs = $catLlm->getSlugPath('es');
    expect($slugPathEs)->toBe('tecnologia/ia-geo/modelos-llm');

    $slugPathEn = $catLlm->getSlugPath('en');
    expect($slugPathEn)->toBe('technology/ai-geo/llm-models');
});

test('category resolver correctly matches deeply nested categories by full path', function (): void {
    $resolved = Category::resolveBySlugPath('tecnologia/ia-geo/modelos-llm', 'es');
    expect($resolved)->not->toBeNull();

    $trans = $resolved->translations->where('locale', 'es')->first();
    expect($trans->name)->toBe('Modelos LLM y agentes');
});

test('nested category page renders visual breadcrumbs and Schema.org BreadcrumbList JSON-LD', function (): void {
    $response = $this->get('/es/es/blog/tecnologia/ia-geo/modelos-llm');
    $response->assertOk();

    // Verify visual breadcrumb elements
    $response->assertSee('Inicio');
    $response->assertSee('Blog');
    $response->assertSee('Tecnología y software', false);
    $response->assertSee('Inteligencia artificial y GEO', false);
    $response->assertSee('Modelos LLM y agentes', false);

    // Verify Schema.org BreadcrumbList JSON-LD
    $response->assertSee('"@type": "BreadcrumbList"', false);
    $response->assertSee('"name": "Modelos LLM y agentes"', false);
});

test('blog post view includes category trail in breadcrumbs', function (): void {
    $post = Post::published()->first();
    $trans = $post->translations->where('locale', 'es')->first();

    $response = $this->get("/es/es/blog/{$trans->slug}");
    $response->assertOk();

    $response->assertSee('Inicio');
    $response->assertSee('Blog');
    $response->assertSee('"@type": "BreadcrumbList"', false);
});
