<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Models\User;
use App\Services\Capabilities\ContentSnapshotService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\ContentAudit\Contracts\Services\DiffEngineServiceInterface;
use Modules\ContentAudit\Models\ContentAudit;
use Modules\ContentAudit\Models\ContentAuditRevision;

final class AuditRevisionService
{
    public function __construct(private readonly ContentSnapshotService $snapshots, private readonly DiffEngineServiceInterface $diffEngine, private readonly AuditEntityPresentation $presentation, private readonly EditorialPolicy $policy) {}

    public function auditFor(Model $entity): ?ContentAudit
    {
        return ContentAudit::where('auditable_type', $entity->getMorphClass())
            ->where('auditable_id', $entity->getKey())
            ->first();
    }

    public function latestId(Model $entity): int
    {
        if (! $this->policy->isEnabled()) {
            return 0;
        }

        return (int) ContentAuditRevision::query()->where('auditable_type', $entity->getMorphClass())->where('auditable_id', $entity->getKey())->max('id');
    }

    /** @param array<string,mixed>|null $snapshot */
    public function record(
        Model $entity,
        ?User $user,
        string $action,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $notes = null,
        ?array $snapshot = null
    ): ContentAuditRevision {
        return DB::transaction(function () use ($entity, $user, $action, $statusBefore, $statusAfter, $notes, $snapshot): ContentAuditRevision {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $audit = $this->auditFor($entity);

            // Previous revision for diff comparison
            /** @var ContentAuditRevision|null $lastRevision */
            $lastRevision = ContentAuditRevision::where('auditable_type', $entity->getMorphClass())
                ->where('auditable_id', $entity->getKey())
                ->orderByDesc('version')
                ->first();

            $nextVersion = ($lastRevision->version ?? 0) + 1;

            $pending = $audit?->pending_revision_id !== null ? $audit->revisions()->whereKey($audit->pending_revision_id)->first() : null;
            $newSnapshot = $snapshot ?? $pending->snapshot ?? $this->snapshots->capture($entity);
            $this->snapshots->assertValid($newSnapshot);

            $diffPayload = null;
            if ($lastRevision && is_array($lastRevision->snapshot)) {
                $diffResult = $this->diffEngine->computeDiff($lastRevision->snapshot, $newSnapshot);
                $diffPayload = $diffResult->toArray();
            }

            $title = $this->presentation->title($entity);

            return ContentAuditRevision::create([
                'content_audit_id' => $audit?->id,
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
                'user_id' => $user?->id,
                'version' => $nextVersion,
                'action' => $action,
                'status_before' => $statusBefore,
                'status_after' => $statusAfter,
                'title' => Str::limit($title, 250),
                'summary' => $this->presentation->actionSummary($action, $user, $title),
                'snapshot' => $newSnapshot,
                'diff' => $diffPayload,
                'notes' => $notes,
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 250),
            ]);
        });
    }

    public function updateLatest(Model $entity, User $actor, int $previousRevisionId): bool
    {
        $snapshot = $this->snapshots->capture($entity);
        $latest = ContentAuditRevision::query()->where('auditable_type', $entity->getMorphClass())->where('auditable_id', $entity->getKey())->orderByDesc('version')->first();
        if ($latest === null || $latest->getKey() <= $previousRevisionId) {
            return false;
        }
        $previous = ContentAuditRevision::query()->where('auditable_type', $entity->getMorphClass())->where('auditable_id', $entity->getKey())->where('version', '<', $latest->version)->orderByDesc('version')->first();
        $latest->snapshot = $snapshot;
        $latest->title = Str::limit($this->presentation->title($entity), 250);
        $latest->summary = $this->presentation->actionSummary($latest->action, $actor, $latest->title);
        $latest->diff = $previous === null ? null : $this->diffEngine->computeDiff((array) $previous->snapshot, $snapshot)->toArray();
        $latest->save();

        return true;
    }

    /** Called under the workflow transaction and entity lock. */
    public function restoreVersion(Model $entity, int $version): void
    {
        $revision = ContentAuditRevision::query()->where('auditable_type', $entity->getMorphClass())->where('auditable_id', $entity->getKey())->where('version', $version)->firstOrFail();
        $this->snapshots->restore($entity, $revision->snapshot);
        $entity->setUpdatedAt(now());
        $entity->saveQuietly();
    }
}
