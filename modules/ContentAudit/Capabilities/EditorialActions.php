<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Capabilities;

use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Models\User;
use App\Services\Capabilities\EntityReadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class EditorialActions
{
    public function __construct(private readonly EditorialWorkflowInterface $audit, private readonly EntityReadService $entities) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function auditApprove(array $args): array
    {
        abort_unless($this->audit->canPublishDirectly($this->actor()), 403, 'Editorial approval permission is required.');
        $entity = $this->entityForAudit($args);
        $audit = $this->audit->approve($entity, $this->actor(), isset($args['notes']) ? (string) $args['notes'] : null);

        return [
            'approved' => true,
            'status' => $audit->status,
            'entity' => ['type' => $this->entities->schemaFor((string) $args['type'])->type, 'id' => $entity->getKey()],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function auditReject(array $args): array
    {
        abort_unless($this->audit->canPublishDirectly($this->actor()), 403, 'Editorial approval permission is required.');
        $entity = $this->entityForAudit($args);
        $reason = trim((string) ($args['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to reject a revision.']);
        }

        $audit = $this->audit->reject($entity, $this->actor(), $reason, isset($args['notes']) ? (string) $args['notes'] : null);

        return ['rejected' => true, 'status' => $audit->status];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function auditRollback(array $args): array
    {
        abort_unless($this->audit->canPublishDirectly($this->actor()), 403, 'Editorial approval permission is required.');
        $entity = $this->entityForAudit($args);
        $targetVersion = (int) ($args['target_version'] ?? 0);

        if ($targetVersion < 1) {
            throw ValidationException::withMessages(['target_version' => 'A positive target_version is required.']);
        }

        $audit = $this->audit->rollback($entity, $targetVersion, $this->actor());

        return [
            'rolled_back' => true,
            'current_version' => $audit->currentVersion,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function entityForAudit(array $args): Model
    {
        $type = (string) ($args['type'] ?? '');
        $id = $args['id'] ?? 0;

        return $this->entities->resolveEntity($type, $id);
    }

    private function actor(): User
    {
        $user = request()->user() ?? Auth::user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user' => 'This operation needs a user-backed token (a personal access token), not a machine client.',
            ]);
        }

        return $user;
    }
}
