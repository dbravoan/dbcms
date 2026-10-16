<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

final class AiMcpFixture
{
    public static function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public static function configure(?User $user = null): User
    {
        $user ??= self::admin();
        $client = app(ClientRepository::class)->createPersonalAccessGrantClient('AI contract tests', 'users');
        $token = Passport::token()->newInstance();
        $token->forceFill(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'client_id' => $client->getKey(), 'name' => 'AI fixture', 'scopes' => ['mcp:access', 'mcp:read', 'mcp:write'], 'revoked' => false, 'expires_at' => now()->addHour()])->save();
        request()->setUserResolver(fn () => $user);
        request()->attributes->add(['oauth_scopes' => ['mcp:access', 'mcp:read', 'mcp:write'], 'oauth_token_id' => $token->getKey(), 'oauth_client_id' => $client->getKey(), 'is_m2m' => false]);
        config(['services.gemini.key' => 'fake-gemini-key', 'services.openai.key' => 'fake-openai-key']);
        Http::preventStrayRequests();

        return $user;
    }
}
