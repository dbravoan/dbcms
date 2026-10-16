<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AiAssistant\Services\AiActionRunner;
use Modules\AiAssistant\Services\AiProviderSettings;
use Tests\Support\AiCompletionFixture;
use Tests\Support\AiComposition;
use Tests\Support\AiMcpFixture;
use Tests\Support\McpProtocolClient;

uses(RefreshDatabase::class, AiComposition::class);

beforeEach(function (): void {
    AiMcpFixture::configure();

});

test('every assistant AI action is exposed as an MCP tool', function (): void {
    app(AiProviderSettings::class)->save('higgsfield', ['api_key_id' => 'fixture-id', 'api_key_secret' => 'fixture-secret']);
    $server = app(McpProtocolClient::class);

    $manifest = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 'ai-parity',
        'method' => 'tools/list',
    ]);

    $names = array_column($manifest['result']['tools'] ?? [], 'name');

    // One MCP tool per canonical AI action: full parity both ways.
    expect(AiActionRunner::MCP_TOOL_ACTIONS)->toHaveCount(count(AiActionRunner::ACTIONS));

    foreach (array_keys(AiActionRunner::MCP_TOOL_ACTIONS) as $tool) {
        expect($names)->toContain($tool);
    }
});

test('mcp ai tools dispatch through the shared action runner', function (): void {
    AiCompletionFixture::configure();
    $server = app(McpProtocolClient::class);

    $calls = [
        'cms_ai_generate_block' => ['block_type' => 'hero', 'prompt' => 'Landing', 'locale' => 'es'],
        'cms_ai_draft_article' => ['topic' => 'Caché en Laravel', 'locale' => 'es'],
        'cms_ai_humanize' => ['content' => 'Texto sintético', 'locale' => 'es'],
        'cms_ai_page_element' => ['element_type' => 'page', 'prompt' => 'Página de servicios', 'locale' => 'es'],
        'cms_ai_proofread' => ['content' => 'Texto a mejorar', 'locale' => 'es'],
        'cms_ai_seo' => ['title' => 'Título', 'content' => 'Contenido', 'locale' => 'es'],
        'cms_ai_recommend_blocks' => ['intent' => 'landing de servicios', 'locale' => 'es'],
    ];

    foreach ($calls as $tool => $args) {
        $response = $server->handle([
            'jsonrpc' => '2.0',
            'id' => $tool,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $args],
        ]);

        if (! array_key_exists('result', $response)) {
            test()->fail("{$tool} failed: ".json_encode($response));
        }

        expect($response['result']['content'][0]['text'] ?? null)->not->toBeEmpty();
    }
});
