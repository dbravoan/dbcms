<?php

declare(strict_types=1);

namespace Modules\ContentAudit\DTOs;

use Modules\ContentAudit\Models\ContentAuditRevision;

final readonly class AuditDiffData
{
    public function __construct(public ContentAuditRevision $targetRevision, public ?ContentAuditRevision $compareRevision, public DiffResultDTO $diffResult) {}

    /** @return array<string,mixed> */
    public function viewData(): array
    {
        return ['targetRevision' => $this->targetRevision, 'compareRevision' => $this->compareRevision, 'diffResult' => $this->diffResult];
    }

    /** @return array<string,mixed> */
    public function jsonData(): array
    {
        return ['target_version' => $this->targetRevision->version, 'compare_version' => $this->compareRevision?->version, 'diff' => $this->diffResult->toArray()];
    }
}
