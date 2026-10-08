<?php

declare(strict_types=1);

use App\Core\Capabilities\CapabilityRegistry;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\Mcp\Services\McpServerService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    mcpTestBindIdentity();
});

test('every tool in the versioned legacy manifest is handled by the server', function (): void {
    $fixture = json_decode((string) File::get(base_path('tests/Fixtures/mcp-legacy-tool-manifest.json')), true, 512, JSON_THROW_ON_ERROR);
    $advertised = array_column($fixture['tools'], 'name');

    expect($advertised)->not->toBeEmpty();

    $supported = McpServerService::SUPPORTED_TOOLS;

    foreach ($advertised as $tool) {
        expect(in_array($tool, $supported, true))->toBeTrue(
            "Tool [{$tool}] belongs to the versioned legacy manifest but is not handled by McpServerService."
        );
    }
});

test('the live tools/list manifest is fully handled by the server', function (): void {
    $server = app(McpServerService::class);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 'manifest',
        'method' => 'tools/list',
    ]);

    $advertised = array_column((array) ($response['result']['tools'] ?? []), 'name');

    expect($advertised)->not->toBeEmpty();

    foreach ($advertised as $tool) {
        expect($server->supportsTool($tool))->toBeTrue(
            "Tool [{$tool}] is advertised by tools/list but not handled by McpServerService."
        );
    }
});

test('every mutating tool is part of the supported tool set', function (): void {
    $server = app(McpServerService::class);
    foreach (app(CapabilityRegistry::class)->all() as $tool) {
        if ($tool->ability() !== 'read') {
            expect($server->supportsTool($tool->name()))->toBeTrue();
        }
    }
});

test('the server exposes the supported tool set', function (): void {
    $server = app(McpServerService::class);

    expect($server->supportsTool('cms_inspect_system'))->toBeTrue()
        ->and($server->supportsTool('cms_create_post'))->toBeTrue()
        ->and($server->supportsTool('cms_does_not_exist'))->toBeFalse();
});
