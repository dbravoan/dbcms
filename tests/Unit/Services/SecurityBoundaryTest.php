<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Capabilities\CapabilityAuthorizer;
use App\Models\User;
use App\Services\Auth\OAuthScopeGrantService;
use Modules\AiAssistant\Services\AiAuthorizationPolicy;
use PHPUnit\Framework\TestCase;

final class SecurityBoundaryTest extends TestCase
{
    public function test_machine_tokens_do_not_bypass_their_service_account_permissions(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isAdmin')->willReturn(false);
        $user->method('hasPermission')->willReturn(false);

        self::assertFalse((new CapabilityAuthorizer)->allowsUser($user, ['mcp:write'], 'write', 'manage:users', 'mcp'));
    }

    public function test_ia_web_access_requires_permission_without_fabricated_oauth_scopes(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isAdmin')->willReturn(false);
        $user->method('hasPermission')->willReturnCallback(fn (string $permission): bool => $permission === 'use:ai');

        $policy = new AiAuthorizationPolicy;
        self::assertTrue($policy->allows($user, 'use:ai', [], false));
        self::assertFalse($policy->allows($user, 'manage:ai', [], false));
        self::assertFalse($policy->allows($user, 'use:ai', [], true));
        self::assertTrue($policy->allows($user, 'use:ai', ['ai:use'], true));
    }

    public function test_public_users_cannot_self_grant_administrative_scopes(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isAdmin')->willReturn(false);
        $user->method('hasPermission')->willReturn(false);

        $grants = new OAuthScopeGrantService;
        self::assertTrue($grants->allows($user, 'read:content'));
        foreach (['*', 'mcp:access', 'mcp:write', 'manage:users', 'admin:system', 'ai:use'] as $scope) {
            self::assertFalse($grants->allows($user, $scope), $scope);
        }
    }
}
