<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Modules\ModuleManager;
use App\Enums\OAuthScope;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;

final readonly class SocialAuthorization
{
    public function __construct(private ModuleManager $modules, private CapabilityAuthorizer $authorizer) {}

    public function installation(): string
    {
        return hash('sha256', (string) config('marketing.installation_id', config('app.key')));
    }

    public function enabled(): bool
    {
        return $this->modules->isModuleEnabled('social-publishing');
    }

    public function authorize(ActorContext $actor, string $permission = 'manage:social-publishing', bool $human = false): User
    {
        abort_unless($this->enabled(), 404);
        $user = $actor->userId === null ? null : User::find($actor->userId);
        abort_unless($user && ($user->isAdmin() || $user->hasPermission($permission)), 403);
        abort_unless($this->liveActor($actor, $permission), 403, 'La identidad, el token o sus permisos ya no están vigentes.');
        if ($human) {
            abort_unless($actor->origin === 'web' && ! $actor->isService && ! (bool) $user->getAttribute('is_service_account') && Auth::guard('web')->id() === $actor->userId, 403, 'La aprobación requiere la sesión humana del revisor.');
        }
        if ($actor->origin === 'api') {
            $scope = match ($permission) {
                'manage:social-accounts' => OAuthScope::SOCIAL_ACCOUNTS->value, 'read:social-publishing' => OAuthScope::SOCIAL_READ->value, default => OAuthScope::SOCIAL_WRITE->value
            };
            $token = $actor->tokenId === null ? null : Passport::token()->find($actor->tokenId);
            $scopes = $token === null ? [] : array_intersect($actor->scopes, (array) $token->getAttribute('scopes'));
            abort_unless(in_array($scope, $scopes, true) || in_array('*', $scopes, true), 403);
        }

        return $user;
    }

    public function liveUser(int $id, string $permission): bool
    {
        $user = User::find($id);

        return $user !== null && ($user->isAdmin() || $user->hasPermission($permission));
    }

    public function liveActor(ActorContext $actor, string $permission): bool
    {
        $ability = $permission === 'read:social-publishing' ? 'read' : 'write';
        if (! $this->authorizer->allowed($actor, $ability, $permission)) {
            return false;
        }
        if ($actor->origin !== 'api') {
            return true;
        }
        $token = $actor->tokenId === null ? null : Passport::token()->find($actor->tokenId);
        $scopes = $token === null ? [] : array_intersect($actor->scopes, (array) $token->getAttribute('scopes'));
        $scope = match ($permission) {
            'manage:social-accounts' => OAuthScope::SOCIAL_ACCOUNTS->value,'read:social-publishing' => OAuthScope::SOCIAL_READ->value,default => OAuthScope::SOCIAL_WRITE->value
        };

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
