<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Modules\ModuleManager;
use Illuminate\Auth\Access\AuthorizationException;

final class CreativeStudioAccess
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer, private readonly ModuleManager $modules) {}

    public function check(ActorContext $actor, string $ability = 'write', string $permission = 'manage:creative-studio'): void
    {
        $authority = $actor;
        if ($permission === 'use:ai' && $actor->origin === 'mcp') {
            $authority = new ActorContext($actor->userId, 'api', $actor->scopes, $actor->clientId, $actor->tokenId, $actor->isService, $actor->market, $actor->locale);
        }
        if (! $this->modules->isModuleEnabled('creative-studio') || ! $this->authorizer->allowed($authority, $ability, $permission)) {
            throw new AuthorizationException('CreativeStudio no está disponible o faltan permisos.');
        }
    }
}
