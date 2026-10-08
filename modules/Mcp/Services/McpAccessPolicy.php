<?php

declare(strict_types=1);

namespace Modules\Mcp\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Models\User;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;

/**
 * Decides whether an agent may run a tool.
 *
 * Two independent gates, both required for user tokens:
 *
 *  1. **OAuth ability** — the token must carry `mcp:read`, `mcp:write` or
 *     `mcp:destructive` (or `*`). `mcp:publish` keeps working as the legacy
 *     alias of `mcp:write`.
 *  2. **Backoffice permission** — the acting user must hold the same permission
 *     the admin screen requires (`manage:shop`, `manage:seo`…), so an agent can
 *     never do more than the person who generated the token.
 *
 * Machine-to-machine tokens (`client_credentials`) belong to an explicitly
 * bound service account. Its role permissions and token scopes are both
 * required, exactly as for a human account.
 */
final class McpAccessPolicy
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer = new CapabilityAuthorizer) {}

    /**
     * @param  list<string>  $scopes
     * @return array{allowed: bool, reason: string|null, required_scope: string, required_permission: string|null}
     */
    public function decide(?User $user, array $scopes, string $ability, ?string $requiredPermission, bool $isM2m = false, ?ActorContext $actor = null): array
    {
        $requiredScope = $this->scopeFor($ability);

        $actor ??= $this->actorFor($user, $scopes, $isM2m);
        $allowed = $actor !== null && $this->authorizer->allowed($actor, $ability, $requiredPermission);

        return [
            'allowed' => $allowed,
            'reason' => $allowed ? null : "The live token or account lacks [{$requiredScope}] and the required [{$requiredPermission}] permission.",
            'required_scope' => $requiredScope,
            'required_permission' => $requiredPermission,
        ];
    }

    public function scopeFor(string $ability): string
    {
        return $this->authorizer->scopeFor($ability);
    }

    /**
     * @param  list<string>  $scopes
     */
    private function actorFor(?User $user, array $scopes, bool $isService): ?ActorContext
    {
        if ($user === null || $user->getKey() === null) {
            return null;
        }
        $token = $user->token();
        if ($token instanceof AccessToken) {
            return new ActorContext((int) $user->getKey(), 'mcp', $scopes, (string) $token->oauth_client_id, (string) $token->oauth_access_token_id, $isService);
        }
        if ($token instanceof Token) {
            return new ActorContext((int) $user->getKey(), 'mcp', $scopes, (string) $token->getAttribute('client_id'), (string) $token->getKey(), $isService);
        }

        return null;
    }
}
