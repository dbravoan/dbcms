<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Modules\ModuleManager;
use App\Models\SystemNotification;
use App\Models\User;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;

final readonly class AuditNotificationRecipientPolicy
{
    public function __construct(private AuditEntityPresentation $presentation, private AuditRevisionService $revisions, private EditorialPolicy $policy, private EntitySchemaRegistryInterface $entities, private ModuleManager $modules, private EditorialVisibilityInterface $visibility) {}

    public function __invoke(SystemNotification $notification, User $recipient): bool
    {
        $data = (array) $notification->data;
        $type = $data['entity_type'] ?? null;
        $id = $data['entity_id'] ?? null;
        if (! is_string($type) || (! is_int($id) && ! is_string($id))) {
            return false;
        }
        $entity = $this->presentation->find($type, $id);
        if ($entity === null) {
            return false;
        }
        $owner = $this->modules->ownerOfClass($entity::class);
        foreach ($this->entities->all() as $schema) {
            if ($entity instanceof $schema->model) {
                $owner = $schema->ownerModule ?? $owner;
                if ($schema->permission === null || (! $recipient->isAdmin() && ! $recipient->hasPermission($schema->permission))) {
                    return false;
                }
                break;
            }
        }
        if ($owner !== null && ! $this->modules->isModuleEnabled($owner)) {
            return false;
        }
        $audit = $this->revisions->auditFor($entity);
        if ($audit === null || ($data['audit_version'] ?? null) !== $audit->current_version) {
            return false;
        }
        if ($notification->type === 'submission') {
            return $this->policy->isEnabled() && $audit->status === 'pending_review' && $this->visibility->canReview($recipient);
        }
        $expected = match ($notification->type) {
            'approval' => 'approved', 'rejection' => 'rejected', default => null,
        };
        $ownerId = $entity->getAttributes()['user_id'] ?? $audit->submitted_by;

        return $expected !== null && $audit->status === $expected && $audit->submitted_by === $recipient->getKey() && (int) $ownerId === (int) $recipient->getKey();
    }
}
