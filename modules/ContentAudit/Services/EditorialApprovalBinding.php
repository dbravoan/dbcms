<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\DTOs\Content\EditorialDecision;
use App\Models\User;
use App\Services\Capabilities\ContentSnapshotService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;

/** A web review token binds the human, target and exact content shown in the form. */
final class EditorialApprovalBinding
{
    public function __construct(private readonly ContentSnapshotService $snapshots, private readonly EditorialWorkflowInterface $workflow, private readonly AuditRevisionService $revisions) {}

    /** @return array{entity:Model,token:string,snapshot:array<string,mixed>} */
    public function review(Model $entity, User $auditor): array
    {
        $this->authorize($entity, $auditor);

        return DB::transaction(function () use ($entity, $auditor): array {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $audit = $this->revisions->auditFor($locked);
            $revision = $audit?->pending_revision_id === null ? null : $audit->revisions()->whereKey($audit->pending_revision_id)->firstOrFail();
            $token = Crypt::encryptString(json_encode(['purpose' => 'review', 'actor' => $auditor->getKey(), 'target' => $locked->getMorphClass(), 'id' => $locked->getKey(), 'hash' => $this->fingerprint($locked), 'expires' => now()->addMinutes(30)->timestamp], JSON_THROW_ON_ERROR));

            return ['entity' => $revision === null ? $locked : $this->snapshots->hydrate($locked, $revision->snapshot), 'token' => $token, 'snapshot' => $revision === null ? $this->snapshots->capture($locked) : $revision->snapshot];
        });
    }

    public function approve(Model $entity, User $auditor, string $token, ?string $notes): EditorialDecision
    {
        return $this->decide($entity, $auditor, $token, fn (Model $locked): EditorialDecision => $this->workflow->approve($locked, $auditor, $notes));
    }

    public function reject(Model $entity, User $auditor, string $token, string $reason, ?string $notes): EditorialDecision
    {
        return $this->decide($entity, $auditor, $token, fn (Model $locked): EditorialDecision => $this->workflow->reject($locked, $auditor, $reason, $notes));
    }

    public function sealRollback(string $reviewToken, int $targetVersion): string
    {
        $binding = json_decode(Crypt::decryptString($reviewToken), true, flags: JSON_THROW_ON_ERROR);
        $binding['purpose'] = 'rollback';
        $binding['target_version'] = $targetVersion;

        return Crypt::encryptString(json_encode($binding, JSON_THROW_ON_ERROR));
    }

    public function rollback(Model $entity, User $auditor, string $token, int $targetVersion): EditorialDecision
    {
        return $this->decide($entity, $auditor, $token, fn (Model $locked): EditorialDecision => $this->workflow->rollback($locked, $targetVersion, $auditor), $targetVersion);
    }

    /** @param \Closure(Model):EditorialDecision $decision */
    private function decide(Model $entity, User $auditor, string $token, \Closure $decision, ?int $targetVersion = null): EditorialDecision
    {
        $this->authorize($entity, $auditor);
        try {
            $binding = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['review_token' => 'Abre la revisión antes de decidir sobre el contenido.']);
        }
        abort_unless(is_array($binding) && ($binding['purpose'] ?? 'review') === ($targetVersion === null ? 'review' : 'rollback') && ($targetVersion === null || ($binding['target_version'] ?? null) === $targetVersion), 409, 'La autorización no corresponde a esta restauración.');
        abort_unless(($binding['actor'] ?? null) === $auditor->getKey() && ($binding['target'] ?? null) === $entity->getMorphClass() && ($binding['id'] ?? null) === $entity->getKey() && is_int($binding['expires'] ?? null) && $binding['expires'] >= now()->timestamp, 409, 'La revisión ha caducado o pertenece a otro auditor.');

        return DB::transaction(function () use ($entity, $binding, $decision): EditorialDecision {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(is_string($binding['hash'] ?? null) && hash_equals($binding['hash'], $this->fingerprint($locked)), 409, 'El contenido cambió desde que abriste la revisión. Revisa la versión actual antes de decidir.');

            return $decision($locked);
        });
    }

    private function fingerprint(Model $entity): string
    {
        $audit = $this->revisions->auditFor($entity);
        $revision = $audit?->pending_revision_id === null ? null : $audit->revisions()->whereKey($audit->pending_revision_id)->firstOrFail();

        return $this->snapshots->hash(['live' => $this->snapshots->capture($entity), 'audit' => $audit?->only(['id', 'status', 'current_version', 'pending_revision_id', 'submitted_by', 'updated_at']), 'revision' => $revision?->only(['id', 'snapshot'])]);
    }

    private function authorize(Model $entity, User $auditor): void
    {
        $permission = null;
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model) {
                $permission = $schema->permission;
                break;
            }
        }
        abort_unless(! $auditor->is_service_account && $permission !== null && app(EditorialVisibilityInterface::class)->canReview($auditor) && app(CapabilityAuthorizer::class)->allowsUser($auditor, [], 'write', $permission, 'web'), 403);
    }
}
