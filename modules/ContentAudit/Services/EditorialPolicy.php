<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;

final class EditorialPolicy
{
    public function __construct(private readonly ModuleSettingsServiceInterface $settingsService, private readonly AuditEntityPresentation $presentation) {}

    public function isEnabled(): bool
    {
        try {
            return app(ModuleManager::class)->isModuleEnabled('content_audit')
                && (bool) $this->settingsService->get('content_audit', 'is_enabled', true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function canPublishDirectly(?User $user = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->hasPermission('audit:approve') || $user->hasPermission('manage:modules')) {
            return true;
        }

        $approverRoles = $this->getConfigList('approver_roles', ['admin', 'editor']);

        return $user->hasRole($approverRoles);
    }

    public function requiresAudit(Model $entity, ?User $user = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $user = $user ?? Auth::user();
        if (! $user) {
            return true;
        }

        if ($this->canPublishDirectly($user)) {
            return false;
        }

        $entityType = $this->presentation->slug($entity);
        $auditedEntities = $this->getConfigList('audited_entities', ['posts', 'pages', 'products', 'galleries']);

        if (! in_array($entityType, $auditedEntities, true)) {
            return false;
        }

        $auditedRoles = $this->getConfigList('audited_roles', ['author', 'marketing']);

        return $user->hasRole($auditedRoles);
    }

    /**
     * Toggle the entity's public visibility flag without tripping strict mode.
     *
     * Posts/pages expose `is_published` (+ `published_at`); products use
     * `is_active`. Entities exposing neither are left untouched.
     */
    public function assertReviewPermission(Model $entity, User $auditor): void
    {
        abort_unless(app(ModuleManager::class)->isModuleEnabled('content_audit'), 404);
        $fresh = User::find($auditor->getKey());
        abort_unless($fresh instanceof User && app(EditorialVisibilityInterface::class)->canReview($fresh)
            && app(CapabilityAuthorizer::class)->allowsUser($fresh, [], 'write', $this->presentation->permission($entity), 'web'), 403);
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    protected function getConfigList(string $key, array $default): array
    {
        try {
            $raw = $this->settingsService->get('content_audit', $key, implode(',', $default));
            $parts = array_map('trim', is_array($raw) ? array_filter($raw, 'is_string') : explode(',', is_string($raw) ? $raw : ''));

            return array_values(array_filter($parts));
        } catch (\Throwable) {
            return $default;
        }
    }
}
