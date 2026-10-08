<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Middleware;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAiAuthorized
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $permission = 'use:ai'): Response
    {
        $user = $request->user();
        $oauth = $request->is('api/*') || $request->bearerToken() !== null;
        $token = $user instanceof User ? $user->token() : null;
        $scopes = $token instanceof AccessToken ? (array) $token->oauth_scopes : [];
        // Tests and native middleware may supply a persisted Passport token.
        if ($token instanceof Token) {
            $scopes = (array) $token->getAttribute('scopes');
        }
        $clientId = $request->attributes->get('oauth_client_id') ?? ($token instanceof AccessToken ? $token->oauth_client_id : ($token instanceof Token ? $token->getAttribute('client_id') : null));
        $tokenId = $request->attributes->get('oauth_token_id') ?? ($token instanceof AccessToken ? $token->oauth_access_token_id : ($token instanceof Token ? $token->getKey() : null));
        $actor = new ActorContext(
            $user instanceof User ? (int) $user->getKey() : null,
            $oauth ? 'api' : 'web', array_values($scopes),
            $clientId !== null ? (string) $clientId : null, $tokenId !== null ? (string) $tokenId : null,
            (bool) $request->attributes->get('is_m2m', $user instanceof User && (bool) $user->getAttribute('is_service_account')),
        );
        $allowed = in_array($permission, ['use:ai', 'manage:ai'], true) && $this->authorizer->allowed($actor, 'write', $permission);
        abort_unless($allowed, $user === null ? 401 : 403, 'You do not have permission to use this AI capability.');

        return $next($request);
    }
}
