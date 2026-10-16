<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Search\SearchIndexer;
use App\Core\Sections\SectionSyncService;
use App\DTOs\Content\EditorialDecision;
use App\DTOs\Content\PendingEditorialDraft;
use App\Models\User;
use App\Services\Capabilities\ContentSnapshotService;
use App\Services\Cms\PageCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Enums\AuditAction;
use Modules\ContentAudit\Enums\AuditStatus;
use Modules\ContentAudit\Events\ContentApprovedEvent;
use Modules\ContentAudit\Events\ContentRejectedEvent;
use Modules\ContentAudit\Events\ContentSubmittedForReviewEvent;
use Modules\ContentAudit\Models\ContentAudit;

final class EditorialWorkflow implements EditorialWorkflowInterface
{
    public function __construct(
        private readonly EditorialPolicy $policy,
        private readonly AuditRevisionService $revisions,
        private readonly EditorialQualityGate $quality,
        private readonly AuditNotifications $notifications,
        private readonly ContentSnapshotService $snapshots,
        private readonly ModuleSettingsServiceInterface $settingsService,
        private readonly PageCacheService $pageCache,
        private readonly EditorialVisibilityInterface $visibility,
        private readonly ModuleManager $modules,
    ) {}

    public function isEnabled(): bool
    {
        return $this->policy->isEnabled();
    }

    public function canPublishDirectly(?User $user = null): bool
    {
        return $this->policy->canPublishDirectly($user);
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
        return $this->policy->requiresAudit($entity, $user);
    }

    public function latestRevisionId(Model $entity): int
    {
        return $this->revisions->latestId($entity);
    }

    public function pendingDraft(Model $entity): ?PendingEditorialDraft
    {
        $audit = $this->revisions->auditFor($entity);
        if ($audit?->pending_revision_id === null) {
            return null;
        }
        $revision = $audit->revisions()->whereKey($audit->pending_revision_id)->firstOrFail();

        return new PendingEditorialDraft((int) $revision->getKey(), $revision->snapshot);
    }

    public function status(Model $entity): ?string
    {
        return $this->revisions->auditFor($entity)?->status;
    }

    public function isEditorialEnforcementEnabled(): bool
    {
        return $this->isEnabled() && $this->quality->isEditorialEnforcementEnabled();
    }

    public function minEditorialScore(): int
    {
        return $this->quality->minEditorialScore();
    }

    public function submitForReview(Model $entity, User $author, ?string $notes = null): ContentAudit
    {
        return DB::transaction(function () use ($entity, $author, $notes): ContentAudit {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            // Keep public status un-published
            if (! $this->revisions->auditFor($entity)?->pending_revision_id) {
                $this->setPublishedFlag($entity, false);
                $this->invalidateEntityCache($entity);
            }

            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::DRAFT->value;

            $audit->status = AuditStatus::PENDING_REVIEW->value;
            $audit->submitted_by = $author->id;
            $audit->submitted_at = now();
            $audit->notes = $notes;
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->save();

            $this->revisions->record(
                $entity,
                $author,
                AuditAction::SUBMITTED->value,
                $statusBefore,
                AuditStatus::PENDING_REVIEW->value,
                $notes
            );

            // Dispatch domain event
            DB::afterCommit(function () use ($entity, $audit, $author, $notes): void {
                event(new ContentSubmittedForReviewEvent($entity, $audit, $author, $notes));
                $this->notifications->submitted($entity, $author, $notes);
            });

            return $audit;
        });
    }

    public function saveAsDraft(Model $entity, User $author): ContentAudit
    {
        return DB::transaction(function () use ($entity, $author): ContentAudit {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            // Drafts never face the public site.
            if (! $this->revisions->auditFor($entity)?->pending_revision_id) {
                $this->setPublishedFlag($entity, false);
                $this->invalidateEntityCache($entity);
            }

            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::DRAFT->value;

            $audit->status = AuditStatus::DRAFT->value;
            $audit->submitted_by = $author->id;
            $audit->submitted_at = null;
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->save();

            $this->revisions->record(
                $entity,
                $author,
                $entity->wasRecentlyCreated ? AuditAction::CREATED->value : AuditAction::UPDATED->value,
                $statusBefore,
                AuditStatus::DRAFT->value
            );

            return $audit;
        });
    }

    /** @param array<string,mixed> $snapshot */
    public function stageDraft(Model $entity, User $author, array $snapshot, string $liveHash, bool $submit = false, ?string $notes = null): PendingEditorialDraft
    {
        return DB::transaction(function () use ($entity, $author, $snapshot, $liveHash, $submit, $notes): PendingEditorialDraft {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $this->quality->enforceSnapshot($entity, $author, $snapshot);
            $audit = ContentAudit::firstOrCreate(['auditable_type' => $entity->getMorphClass(), 'auditable_id' => $entity->getKey()], ['status' => AuditStatus::DRAFT->value, 'current_version' => 0]);
            $snapshot['_live_hash'] = $liveHash;
            $revision = $this->revisions->record($entity, $author, AuditAction::UPDATED->value, $audit->status, AuditStatus::DRAFT->value, snapshot: $snapshot);
            $audit->fill(['status' => AuditStatus::DRAFT->value, 'submitted_by' => $author->getKey(), 'submitted_at' => null, 'pending_revision_id' => $revision->getKey(), 'current_version' => $revision->version])->save();

            if ($submit) {
                $this->submitForReview($entity, $author, $notes);
            }

            return new PendingEditorialDraft((int) $revision->getKey(), $snapshot);
        });
    }

    public function recordDeletion(Model $entity, User $actor): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        DB::transaction(function () use ($entity, $actor): void {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $audit = ContentAudit::firstOrNew(['auditable_type' => $entity->getMorphClass(), 'auditable_id' => $entity->getKey()]);
            $before = $audit->getAttributes()['status'] ?? AuditStatus::DRAFT->value;
            $audit->status = AuditStatus::DELETED->value;
            $audit->pending_revision_id = null;
            $audit->current_version = (int) ($audit->current_version ?? 0) + 1;
            $audit->save();
            $this->revisions->record($entity, $actor, AuditAction::DELETED->value, $before, AuditStatus::DELETED->value);
        });
    }

    public function completeMutation(Model $entity, User $actor, int $previousRevisionId, ?string $editorialAction = null, ?string $notes = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        $snapshot = $this->snapshots->capture($entity);
        $this->quality->enforceSnapshot($entity, $actor, $snapshot);
        $restricted = $this->requiresAudit($entity, $actor);
        $state = $this->revisions->auditFor($entity)?->status;
        if (($editorialAction === 'submit_for_review' || ($restricted && $editorialAction !== 'draft')) && $state !== AuditStatus::PENDING_REVIEW->value) {
            $this->submitForReview($entity, $actor, $notes);
        } elseif ($editorialAction === 'draft' && $state !== AuditStatus::DRAFT->value) {
            $this->saveAsDraft($entity, $actor);
        }
        if (! $this->revisions->updateLatest($entity, $actor, $previousRevisionId)) {
            if ($restricted) {
                $this->saveAsDraft($entity, $actor);
            } elseif ((bool) $this->settingsService->get('content_audit', 'track_all_modifications', true)) {
                $this->revisions->record($entity, $actor, AuditAction::UPDATED->value);
            }
        }
    }

    public function approve(Model $entity, User $auditor, ?string $notes = null, bool $publish = false): EditorialDecision
    {
        $this->policy->assertReviewPermission($entity, $auditor);

        return DB::transaction(function () use ($entity, $auditor, $notes, $publish): EditorialDecision {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $entity->setRawAttributes($locked->getAttributes(), true);
            $entity->unsetRelations();
            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::PENDING_REVIEW->value;
            $approvedVisibility = null;
            $appliedRevision = false;

            $autoPublish = $publish || (bool) $this->settingsService->get('content_audit', 'auto_publish_on_approval', true);
            if ($audit->pending_revision_id !== null) {
                $revision = $audit->revisions()->whereKey($audit->pending_revision_id)->firstOrFail();
                $snapshots = app(ContentSnapshotService::class);
                abort_unless(($revision->snapshot['_live_hash'] ?? null) === $snapshots->hash($snapshots->capture($entity->refresh())), 409, 'The live content changed after this draft was created.');
                if ($autoPublish) {
                    $snapshots->restore($entity, $revision->snapshot);
                    $appliedRevision = true;
                    $intent = $revision->snapshot['_visibility_intent'] ?? null;
                    if (is_array($intent) && in_array($intent['field'] ?? null, ['is_published', 'is_active'], true) && is_bool($intent['value'] ?? null)) {
                        $approvedVisibility = $intent['value'];
                    }
                    $audit->pending_revision_id = null;
                }
            }

            $audit->status = AuditStatus::APPROVED->value;
            $audit->reviewed_by = $auditor->id;
            $audit->reviewed_at = now();
            $audit->rejection_reason = null;
            if ($notes !== null) {
                $audit->notes = $notes;
            }
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->save();

            if ($autoPublish) {
                $this->setPublishedFlag($entity, $approvedVisibility ?? true);
                app(SectionSyncService::class)->publish($entity);
            }
            if ($autoPublish || $appliedRevision) {
                $this->invalidateEntityCache($entity);
            }

            $this->revisions->record(
                $entity,
                $auditor,
                AuditAction::APPROVED->value,
                $statusBefore,
                AuditStatus::APPROVED->value,
                $notes
            );

            // Dispatch domain event
            DB::afterCommit(function () use ($entity, $audit, $auditor, $notes): void {
                event(new ContentApprovedEvent($entity, $audit, $auditor, $notes));
                $this->notifications->decided($entity, $audit, $auditor, true);
            });

            return new EditorialDecision($audit->status, (int) $audit->current_version, $audit->pending_revision_id);
        });
    }

    public function reject(Model $entity, User $auditor, string $reason, ?string $notes = null): EditorialDecision
    {
        $this->policy->assertReviewPermission($entity, $auditor);

        return DB::transaction(function () use ($entity, $auditor, $reason, $notes): EditorialDecision {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $entity->setRawAttributes($locked->getAttributes(), true);
            $entity->unsetRelations();
            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::PENDING_REVIEW->value;
            $hadPending = $audit->pending_revision_id !== null;

            $audit->status = AuditStatus::REJECTED->value;
            $audit->reviewed_by = $auditor->id;
            $audit->reviewed_at = now();
            $audit->rejection_reason = $reason;
            if ($notes !== null) {
                $audit->notes = $notes;
            }
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->save();

            // Ensure unpublished
            if (! $hadPending) {
                $this->setPublishedFlag($entity, false);
                $this->invalidateEntityCache($entity);
            }

            $this->revisions->record(
                $entity,
                $auditor,
                AuditAction::REJECTED->value,
                $statusBefore,
                AuditStatus::REJECTED->value,
                "Motivo de rechazo: {$reason}"
            );

            if ($hadPending) {
                // Archive the rejected snapshot first, then allow a fresh draft from live content.
                $audit->pending_revision_id = null;
                $audit->save();
            }

            // Dispatch domain event
            DB::afterCommit(function () use ($entity, $audit, $auditor, $reason, $notes): void {
                event(new ContentRejectedEvent($entity, $audit, $auditor, $reason, $notes));
                $this->notifications->decided($entity, $audit, $auditor, false, $reason);
            });

            return new EditorialDecision($audit->status, (int) $audit->current_version, $audit->pending_revision_id);
        });
    }

    public function rollback(Model $entity, int $targetVersion, User $auditor): EditorialDecision
    {
        $this->policy->assertReviewPermission($entity, $auditor);

        return DB::transaction(function () use ($entity, $targetVersion, $auditor): EditorialDecision {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $entity->setRawAttributes($locked->getAttributes(), true);
            $entity->unsetRelations();
            $this->revisions->restoreVersion($entity, $targetVersion);

            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::DRAFT->value;
            $audit->pending_revision_id = null;
            $audit->status = AuditStatus::DRAFT->value;
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->notes = "Revertido a la versión {$targetVersion} por {$auditor->name}";
            $audit->save();

            $this->invalidateEntityCache($entity);

            $this->revisions->record(
                $entity,
                $auditor,
                AuditAction::ROLLBACK->value,
                $statusBefore,
                AuditStatus::DRAFT->value,
                "Restaurada versión histórica #{$targetVersion}"
            );

            return new EditorialDecision($audit->status, (int) $audit->current_version, $audit->pending_revision_id);
        });
    }

    /**
     * Drops every cached public page that depends on this entity.
     *
     * Editorial decisions change the public site through `saveQuietly()`, which
     * does not fire the PageCacheObserver, so the invalidation must be explicit.
     */
    protected function invalidateEntityCache(Model $entity): void
    {
        $table = $entity->getTable();
        $id = $entity->getKey();
        $type = null;
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model) {
                $type = $schema->type;
                break;
            }
        }
        DB::afterCommit(function () use ($table, $id, $type): void {
            try {
                $this->pageCache->invalidateDependencies([
                    $this->pageCache->dependencyKey($table, (string) $id),
                    $this->pageCache->typeDependencyKey($table),
                ]);
            } catch (\Throwable) {
                // Cache failures must never undo an editorial decision.
            }
            if ($type !== null) {
                app(SearchIndexer::class)->indexEntity($type, $id);
            }
        });
    }

    protected function setPublishedFlag(Model $entity, bool $published): void
    {
        $attributes = $entity->getAttributes();

        if (array_key_exists('is_published', $attributes)) {
            $entity->setAttribute('is_published', $published);
        } elseif (array_key_exists('is_active', $attributes)) {
            $entity->setAttribute('is_active', $published);
        } else {
            return;
        }

        if ($published && array_key_exists('published_at', $attributes) && empty($attributes['published_at'])) {
            $entity->setAttribute('published_at', now());
        }

        $entity->saveQuietly();
    }
}
