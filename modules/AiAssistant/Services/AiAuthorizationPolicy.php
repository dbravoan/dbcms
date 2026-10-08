<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Capabilities\CapabilityAuthorizer;
use App\Models\User;

final class AiAuthorizationPolicy
{
    /** @param list<string> $scopes */
    public function allows(?User $user, string $permission, array $scopes, bool $oauth): bool
    {
        if (! in_array($permission, ['use:ai', 'manage:ai'], true) || $user === null) {
            return false;
        }

        return (new CapabilityAuthorizer)->allowsUser($user, $scopes, 'write', $permission, $oauth ? 'api' : 'web');
    }
}
