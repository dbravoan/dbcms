<?php

declare(strict_types=1);

use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;
use Modules\Mcp\Services\McpServerService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    mcpTestBindIdentity();
});

function callMcpTool(string $tool, array $arguments = []): array
{
    $response = app(McpServerService::class)->handle([
        'jsonrpc' => '2.0',
        'id' => 'test',
        'method' => 'tools/call',
        'params' => ['name' => $tool, 'arguments' => $arguments],
    ]);

    if (isset($response['error'])) {
        return ['error' => $response['error']['message'] ?? 'error'];
    }

    $text = (string) ($response['result']['content'][0]['text'] ?? '');

    return (array) json_decode($text, true);
}

test('the webmcp filter tools are part of the server manifest', function (): void {
    $response = app(McpServerService::class)->handle([
        'jsonrpc' => '2.0',
        'id' => 'list',
        'method' => 'tools/list',
    ]);

    $advertised = array_column((array) ($response['result']['tools'] ?? []), 'name');

    expect($advertised)->toContain('cms_list_filter_dimensions')
        ->toContain('cms_resolve_filter_url')
        ->toContain('cms_browse_content');
});

test('an agent can discover the filter dimensions and their localized markers', function (): void {
    $dimensions = callMcpTool('cms_list_filter_dimensions', ['module' => 'blog', 'market' => 'es', 'locale' => 'es']);

    $keys = array_column($dimensions['modules']['blog']['dimensions'] ?? [], 'key');

    expect($keys)->toContain('category')
        ->toContain('tag')
        ->toContain('sort');

    $tag = collect($dimensions['modules']['blog']['dimensions'])->firstWhere('key', 'tag');

    expect($tag['marker'])->toBe('etiqueta')
        ->and($tag['type'])->toBe('terms');
});

test('an agent can resolve a friendly path into its canonical url and policy', function (): void {
    $post = Post::published()->firstOrFail();
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $post->tags()->attach($tag->getKey());

    $resolved = callMcpTool('cms_resolve_filter_url', [
        'module' => 'blog',
        'path' => 'etiqueta/laravel/orden/recientes',
        'market' => 'es',
        'locale' => 'es',
    ]);

    expect($resolved)->toHaveKey('intro')
        ->and($resolved['canonical_path'])->toBe('laravel/recientes')
        ->and($resolved['indexable'])->toBeFalse()
        ->and($resolved['robots'])->toBe('noindex, follow')
        ->and($resolved['results'])->toBeGreaterThan(0)
        ->and($resolved['canonical_url'])->toContain('/es/es/blog/laravel/recientes')
        ->and(collect($resolved['alternates'])->pluck('hreflang'))->toContain('x-default');
});

test('an invalid path is reported instead of served', function (): void {
    $resolved = callMcpTool('cms_resolve_filter_url', [
        'module' => 'blog',
        'path' => 'etiqueta/no-existe',
        'market' => 'es',
        'locale' => 'es',
    ]);

    expect($resolved)->toHaveKey('error');
});

test('an agent can browse the catalogue through a friendly path or a filters map', function (): void {
    $post = Post::published()->firstOrFail();
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $post->tags()->attach($tag->getKey());

    $byPath = callMcpTool('cms_browse_content', ['module' => 'blog', 'path' => 'etiqueta/laravel', 'market' => 'es', 'locale' => 'es']);

    expect($byPath['total'])->toBeGreaterThan(0)
        ->and($byPath['items'])->not->toBeEmpty()
        ->and($byPath['canonical_url'])->toContain('/es/es/blog/laravel');

    $byFilters = callMcpTool('cms_browse_content', ['module' => 'blog', 'filters' => ['tag' => ['laravel']], 'market' => 'es', 'locale' => 'es']);

    expect($byFilters['total'])->toBe($byPath['total'])
        ->and($byFilters['canonical_url'])->toBe($byPath['canonical_url']);
});

test('the shop catalogue is browsable too', function (): void {
    $browse = callMcpTool('cms_browse_content', ['module' => 'shop', 'filters' => ['sort' => 'precio-asc'], 'market' => 'es', 'locale' => 'es']);

    expect($browse)->toHaveKey('total')
        ->and($browse['canonical_url'])->toContain('/es/es/tienda/precio-asc');
});
