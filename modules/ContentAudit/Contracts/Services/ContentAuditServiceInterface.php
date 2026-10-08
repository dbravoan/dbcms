<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Contracts\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\ContentAudit\Models\ContentAudit;
use Modules\ContentAudit\Models\ContentAuditRevision;

interface ContentAuditServiceInterface
{
    public function isEnabled(): bool;

    public function canPublishDirectly(?User $user = null): bool;

    public function requiresAudit(Model $entity, ?User $user = null): bool;

    public function getAudit(Model $entity): ?ContentAudit;

    public function submitForReview(Model $entity, User $author, ?string $notes = null): ContentAudit;

    /**
     * Saves the entity as a draft, taking it out of the review queue if it was pending.
     */
    public function saveAsDraft(Model $entity, User $author): ContentAudit;

    /** @param array<string,mixed> $snapshot */
    public function stageSnapshot(Model $entity, User $author, array $snapshot, string $liveHash, bool $submit = false, ?string $notes = null): ContentAudit;

    /**
     * Binding editorial quality gate: throws a ValidationException when the
     * enforced quality bar is not met and the acting user is subject to audit.
     *
     * @throws ValidationException
     */
    public function enforceEditorialQuality(Model $entity, User $user, bool $requiresAudit): void;

    /** @param array<string,mixed> $snapshot */
    public function enforceEditorialSnapshot(Model $entity, User $user, array $snapshot): void;

    public function completeMutation(Model $entity, User $actor, int $previousRevisionId, ?string $editorialAction = null, ?string $notes = null): void;

    public function latestRevisionId(Model $entity): int;

    public function recordDeletion(Model $entity, User $actor): void;

    public function isEditorialEnforcementEnabled(): bool;

    public function minEditorialScore(): int;

    public function approve(Model $entity, User $auditor, ?string $notes = null, bool $publish = false): ContentAudit;

    public function reject(Model $entity, User $auditor, string $reason, ?string $notes = null): ContentAudit;

    public function recordChange(
        Model $entity,
        ?User $user,
        string $action,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $notes = null
    ): ContentAuditRevision;

    public function rollback(Model $entity, int $targetVersion, User $auditor): ContentAudit;

    /**
     * Captures a unified historical snapshot of an entity including its translations.
     *
     * @return array<string, mixed>
     */
    public function captureSnapshot(Model $entity): array;

    public function resolveEntitySlug(Model $entity): string;

    public function resolveEntityFromSlug(string $type, int|string $id): ?Model;

    /**
     * Build a strict-mode safe, normalized description of an auditable entity.
     *
     * Reads raw attributes only, so it never trips `Model::shouldBeStrict()`
     * when an entity (or one of its translations) lacks a given column
     * (e.g. products use `name`/`is_active`, pages have no `content`).
     *
     * @return array{
     *     title: string,
     *     slug: string|null,
     *     is_published: bool,
     *     translations: Collection<int, array{locale: string, title: string, slug: string, summary: string, content: string, meta_title: string, meta_description: string}>
     * }
     */
    public function describeEntity(Model $entity): array;
}
