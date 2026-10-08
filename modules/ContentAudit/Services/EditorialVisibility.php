<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Models\ContentAuditRevision;

final class EditorialVisibility implements EditorialVisibilityInterface
{
    public function __construct(private readonly EntitySchemaRegistryInterface $entities, private readonly ModuleManager $modules, private readonly ModuleSettingsServiceInterface $settings) {}

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     */
    public function constrain(Builder $query, User $user): void
    {
        $fresh = User::find((int) $user->getKey());
        $allowed = [];
        if ($fresh !== null && ! $fresh->is_service_account && ($fresh->isAdmin() || $fresh->hasPermission('access:admin'))) {
            foreach ($this->entities->all() as $entity) {
                $module = $entity->ownerModule ?? $this->modules->ownerOfClass($entity->model);
                if ($entity->permission !== null && ($fresh->isAdmin() || $fresh->hasPermission($entity->permission))
                    && ($module === null || $this->modules->isModuleEnabled($module))) {
                    /** @var class-string<Model> $modelClass */
                    $modelClass = $entity->model;
                    $allowed[] = (new $modelClass)->getMorphClass();
                }
            }
        }
        if ($allowed === [] || ! $fresh instanceof User) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn('auditable_type', $allowed);
        if (! $this->canReview($fresh)) {
            // Authors see their own submissions and versions; reviewers see the
            // entities for which they hold an explicit management permission.
            $query->where($query->getModel() instanceof ContentAuditRevision ? 'user_id' : 'submitted_by', $fresh->getKey());
        }
    }

    public function canReview(User $user): bool
    {
        $fresh = User::find((int) $user->getKey());
        if ($fresh === null || $fresh->is_service_account || (! $fresh->isAdmin() && ! $fresh->hasPermission('access:admin'))) {
            return false;
        }
        $configured = $this->settings->get('content_audit', 'approver_roles', ['admin', 'editor']);
        $roles = array_values(is_array($configured) ? array_filter($configured, 'is_string') : array_filter(array_map('trim', explode(',', is_string($configured) ? $configured : ''))));

        return $fresh->isAdmin() || $fresh->hasPermission('audit:approve') || $fresh->hasPermission('manage:modules') || $fresh->hasRole($roles);
    }

    public function authorizeRevision(ContentAuditRevision $revision, User $user): void
    {
        $query = ContentAuditRevision::query()->whereKey($revision->getKey());
        $this->constrain($query, $user);
        abort_unless($query->exists(), 403);
    }
}
