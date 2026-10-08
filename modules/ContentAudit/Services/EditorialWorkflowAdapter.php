<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Modules\ModuleManager;
use App\DTOs\Content\EditorialDecision;
use App\DTOs\Content\PendingEditorialDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Models\ContentAudit;

final class EditorialWorkflowAdapter implements EditorialWorkflowInterface
{
    public function __construct(private readonly ContentAuditServiceInterface $audits, private readonly EditorialVisibilityInterface $visibility, private readonly ModuleManager $modules) {}

    public function isEnabled(): bool
    {
        return $this->audits->isEnabled();
    }

    public function canPublishDirectly(?User $user = null): bool
    {
        return $this->audits->canPublishDirectly($user);
    }

    public function canReview(User $user): bool
    {
        return $this->isReviewAvailable() && $this->visibility->canReview($user);
    }

    public function pendingReviewCount(User $user): int
    {
        if (! $this->isReviewAvailable()) {
            return 0;
        }
        $pending = ContentAudit::query()->where('status', 'pending_review');
        $this->visibility->constrain($pending, $user);

        return $pending->count();
    }

    private function isReviewAvailable(): bool
    {
        return $this->modules->isModuleEnabled('content_audit') && Schema::hasTable('content_audits');
    }

    public function requiresAudit(Model $entity, ?User $user = null): bool
    {
        return $this->audits->requiresAudit($entity, $user);
    }

    public function latestRevisionId(Model $entity): int
    {
        return $this->audits->latestRevisionId($entity);
    }

    public function completeMutation(Model $entity, User $actor, int $previousRevisionId, ?string $editorialAction = null, ?string $notes = null): void
    {
        $this->audits->completeMutation($entity, $actor, $previousRevisionId, $editorialAction, $notes);
    }

    public function recordDeletion(Model $entity, User $actor): void
    {
        $this->audits->recordDeletion($entity, $actor);
    }

    public function pendingDraft(Model $entity): ?PendingEditorialDraft
    {
        $audit = $this->audits->getAudit($entity);
        if ($audit?->pending_revision_id === null) {
            return null;
        }
        $revision = $audit->revisions()->whereKey($audit->pending_revision_id)->firstOrFail();

        return new PendingEditorialDraft((int) $revision->getKey(), $revision->snapshot);
    }

    public function stageDraft(Model $entity, User $actor, array $snapshot, string $liveHash, bool $submit = false, ?string $notes = null): PendingEditorialDraft
    {
        $this->audits->stageSnapshot($entity, $actor, $snapshot, $liveHash, $submit, $notes);

        return $this->pendingDraft($entity) ?? throw new \LogicException('The editorial handler did not persist a draft.');
    }

    public function approve(Model $entity, User $auditor, ?string $notes = null, bool $publish = false): EditorialDecision
    {
        return $this->decision($this->audits->approve($entity, $auditor, $notes, $publish));
    }

    public function reject(Model $entity, User $auditor, string $reason, ?string $notes = null): EditorialDecision
    {
        return $this->decision($this->audits->reject($entity, $auditor, $reason, $notes));
    }

    public function rollback(Model $entity, int $targetVersion, User $auditor): EditorialDecision
    {
        return $this->decision($this->audits->rollback($entity, $targetVersion, $auditor));
    }

    public function status(Model $entity): ?string
    {
        return $this->audits->getAudit($entity)?->status;
    }

    public function isEditorialEnforcementEnabled(): bool
    {
        return $this->isEnabled() && $this->audits->isEditorialEnforcementEnabled();
    }

    public function minEditorialScore(): int
    {
        return $this->audits->minEditorialScore();
    }

    private function decision(ContentAudit $audit): EditorialDecision
    {
        return new EditorialDecision($audit->status, (int) $audit->current_version, $audit->pending_revision_id);
    }
}
