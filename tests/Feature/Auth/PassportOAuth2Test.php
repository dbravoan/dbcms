<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    app(ClientRepository::class)->createPersonalAccessGrantClient('Testing Client');
});

test('users can register via api and receive 201 response', function (): void {
    $payload = [
        'name' => 'Alice Agent',
        'email' => 'alice@example.com',
        'password' => 'SecurePassw0rd!123',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Alice Agent')
        ->assertJsonPath('data.email', 'alice@example.com');

    $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
});

test('users can authenticate and receive a valid oauth2 access token', function (): void {
    $user = User::factory()->create([
        'email' => 'bob@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
        'scopes' => ['read:content'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
            'expires_at',
        ]);
});

test('authenticated users can revoke active passport tokens on logout', function (): void {
    $user = User::factory()->create();
    Passport::actingAs($user, ['read:content']);

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Tokens revoked successfully.']);
});
