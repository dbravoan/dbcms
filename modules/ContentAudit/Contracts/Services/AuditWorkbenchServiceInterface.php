<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Contracts\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\ContentAudit\DTOs\AuditDiffData;

interface AuditWorkbenchServiceInterface
{
    /** @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function index(User $user, array $filters): array;

    public function diff(User $user, int $revisionId, mixed $compareWithId): AuditDiffData;

    /** @return array<string,mixed> */
    /** @param array<string,mixed> $values */
    public function updateSettings(array $values): void;

    /** @return array<string,mixed> */
    public function sidebar(Model $entity, ?User $user): array;
}
