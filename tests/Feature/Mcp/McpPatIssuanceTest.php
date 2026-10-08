<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('the foundation seeder provisions a passport personal access client', function (): void {
    expect(Client::query()->where('grant_types', 'like', '%personal_access%')->exists())->toBeTrue();
});

test('cms:issue-mcp-token issues a token that the mcp endpoint accepts', function (): void {
    $this->artisan('cms:issue-mcp-token', ['--name' => 'PAT Smoke'])->assertSuccessful();

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $tokenResult = $admin->createToken('PAT Smoke', ['mcp:access', 'mcp:publish']);

    $response = $this->withToken($tokenResult->accessToken)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->json('result.tools'))->toBeArray()->not->toBeEmpty();
});
