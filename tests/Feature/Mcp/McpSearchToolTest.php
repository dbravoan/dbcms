<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Search\SearchIndexer;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Tests\Support\CmsFixtures;
use Tests\Support\McpFixtures;
use Tests\Support\McpProtocolClient;
use Tests\Support\McpSearchComposition;

uses(RefreshDatabase::class, McpSearchComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();
    CmsFixtures::localization(['es', 'en'], ['es', 'us']);
    $post = Post::create(['user_id' => User::where('email', 'admin@db-cms.io')->firstOrFail()->id, 'is_published' => true, 'published_at' => now()]);
    $post->translations()->create(['locale' => 'es', 'title' => 'Arquitectura modular', 'slug' => 'arquitectura', 'content' => 'Documentación de arquitectura modular.']);
    $post->markets()->attach(Market::where('code', 'es')->firstOrFail());
    mcpTestBindIdentity();
    app(SearchIndexer::class)->indexAll();
});

function callTool(string $name, array $args): array
{
    $response = app(McpProtocolClient::class)->handle([
        'jsonrpc' => '2.0',
        'id' => 'test',
        'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => $args],
    ]);

    // MCP wraps tool payloads as text content: decode it back.
    $text = (string) ($response['result']['content'][0]['text'] ?? '');

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($text, true) ?? [];

    return $decoded;
}

test('cms_search is advertised and handled by the server', function (): void {
    $manifest = app(McpProtocolClient::class)->handle([
        'jsonrpc' => '2.0',
        'id' => 'search-manifest',
        'method' => 'tools/list',
    ]);
    $names = array_column((array) ($manifest['result']['tools'] ?? []), 'name');

    expect($names)->toContain('cms_search');
});

test('cms_search returns ranked results with their canonical urls', function (): void {
    $result = callTool('cms_search', ['query' => 'arquitectura']);

    expect($result['total'])->toBeGreaterThan(0)
        ->and($result['canonical_url'])->toContain('/es/es/buscar/arquitectura')
        ->and($result['hub_url'])->toContain('/es/es/buscar')
        ->and($result['results'])->not->toBeEmpty();

    $first = $result['results'][0];

    expect($first)->toHaveKeys(['type', 'label', 'title', 'excerpt', 'url', 'relevance'])
        ->and($first['url'])->toContain('/es/es/');
});

test('cms_search reports facets, entity filtering and unknown types', function (): void {
    $all = callTool('cms_search', ['query' => 'arquitectura']);
    expect($all['facets'])->toHaveKey('post');

    $typed = callTool('cms_search', ['query' => 'arquitectura', 'type' => 'post', 'limit' => 2]);
    expect($typed['total'])->toBeLessThanOrEqual(2)
        ->and($typed['results'])->toHaveCount($typed['total']);

    $unknown = callTool('cms_search', ['query' => 'arquitectura', 'type' => 'no-existe']);
    expect($unknown)->toHaveKey('error')
        ->and($unknown['available_types'])->toContain('post');
});

test('cms_search tells agents whether the result page is citable', function (): void {
    // Enough indexed content for the page to clear the quality gate.
    $indexer = app(SearchIndexer::class);

    foreach (range(1, 3) as $i) {
        $post = Post::withoutEvents(fn () => Post::create(['user_id' => User::query()->firstOrFail()->getKey(), 'is_published' => true, 'published_at' => now()]));
        $post->translations()->create(['locale' => 'es', 'title' => 'Contenido citable '.$i, 'slug' => 'citable-'.$i, 'content' => 'Contenido público citable '.$i]);
        $post->markets()->attach(Market::where('code', 'es')->firstOrFail());
        $indexer->indexEntity('post', $post->getKey());
    }

    $before = callTool('cms_search', ['query' => 'contenido citable']);
    expect($before['index_policy'])->toBe('noindex');

    app(ModuleSettingsServiceInterface::class)->set('search', 'is_indexable', true);

    $after = callTool('cms_search', ['query' => 'contenido citable']);
    expect($after['index_policy'])->toBe('index');

    // With too little content the page is not citable even when activated.
    $thin = callTool('cms_search', ['query' => 'arquitectura']);
    expect($thin['index_policy'])->toBe('noindex')
        ->and($thin['canonical_url'])->toContain('/es/es/buscar/arquitectura');
});

test('cms_search suggests alternatives when nothing matches', function (): void {
    $result = callTool('cms_search', ['query' => 'zzz arquitectura']);

    expect($result['total'])->toBe(0)
        ->and($result['suggestions'])->not->toBeEmpty();
});

test('cms_search requires a query', function (): void {
    $result = callTool('cms_search', []);

    expect($result)->toHaveKey('error')
        ->and($result['validation'])->toHaveKey('query');
});
