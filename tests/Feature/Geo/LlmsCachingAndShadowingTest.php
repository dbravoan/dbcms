<?php

declare(strict_types=1);

use App\Contracts\Services\GeoServiceInterface;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Pages\Models\Page;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Cache::flush();
});

test('/llms.txt is cached server-side across requests', function (): void {
    $mock = Mockery::mock(GeoServiceInterface::class);
    $mock->shouldReceive('generateLlmsTxt')->once()->andReturn('CACHED-LLMS-BODY');
    $this->app->instance(GeoServiceInterface::class, $mock);

    $this->get('/llms.txt')->assertOk()->assertSee('CACHED-LLMS-BODY');
    // Second request must be served from cache → the generator runs only once.
    $this->get('/llms.txt')->assertOk()->assertSee('CACHED-LLMS-BODY');
});

test('a page with slug "llms" cannot shadow the /llms.txt endpoint', function (): void {
    $this->seed(PlatformShowcaseSeeder::class);

    $page = Page::create([
        'template' => 'default',
        'is_published' => true,
    ]);
    $page->translations()->create([
        'locale' => 'es',
        'title' => 'LLMS Shadow',
        'slug' => 'llms',
    ]);

    // The endpoint must keep serving the generated plain-text knowledge base,
    // not the HTML page that shares the slug.
    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('Regional Knowledge Bases');
});
