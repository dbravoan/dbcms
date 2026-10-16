<?php

declare(strict_types=1);

use App\Models\Market;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;
use Modules\Shop\Models\ProductCategory;
use Tests\Support\CmsFixtures;
use Tests\Support\FilterFixtures;
use Tests\Support\McpFilterComposition;
use Tests\Support\McpFixtures;
use Tests\Support\McpProtocolClient;

uses(RefreshDatabase::class, McpFilterComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();
    CmsFixtures::localization(['es', 'en'], ['es', 'us']);
    FilterFixtures::blog();
    FilterFixtures::shop();
    mcpTestBindIdentity();
});

function callMcpTool(string $tool, array $arguments = []): array
{
    $response = app(McpProtocolClient::class)->handle([
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
    $response = app(McpProtocolClient::class)->handle([
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
        'path' => 'laravel/recientes',
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
        'path' => 'no-existe/otro',
        'market' => 'es',
        'locale' => 'es',
    ]);

    expect($resolved)->toHaveKey('error');
});

test('an agent can browse the catalogue through a friendly path or a filters map', function (): void {
    $post = Post::published()->firstOrFail();
    $tag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $post->tags()->attach($tag->getKey());

    $byPath = callMcpTool('cms_browse_content', ['module' => 'blog', 'path' => 'laravel', 'market' => 'es', 'locale' => 'es']);

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

test('agent category vocabularies use the selected market rather than request-global state', function (string $module): void {
    $spain = Market::query()->where('code', 'es')->sole();
    app()->instance('current.market', $spain);
    $class = $module === 'blog' ? Category::class : ProductCategory::class;
    $category = $class::create(['is_active' => true]);
    $category->translations()->create(['locale' => 'en', 'name' => 'Selected market taxonomy', 'slug' => 'selected-market-taxonomy']);
    $category->markets()->sync([$spain->getKey()]);

    $local = callMcpTool('cms_list_filter_dimensions', ['module' => $module, 'market' => 'es', 'locale' => 'en']);
    $foreign = callMcpTool('cms_list_filter_dimensions', ['module' => $module, 'market' => 'us', 'locale' => 'en']);
    expect(json_encode($local, JSON_THROW_ON_ERROR))->toContain('Selected market taxonomy');
    expect(json_encode($foreign, JSON_THROW_ON_ERROR))->not->toContain('Selected market taxonomy');
})->with(['blog', 'shop']);
