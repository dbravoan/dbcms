<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Contracts\Services;

use Modules\ContentAudit\DTOs\DiffResultDTO;

interface DiffEngineServiceInterface
{
    /**
     * Compute field-level and line-level diff between two entity snapshots.
     *
     * @param  array<string, mixed>  $oldSnapshot
     * @param  array<string, mixed>  $newSnapshot
     */
    public function computeDiff(array $oldSnapshot, array $newSnapshot): DiffResultDTO;

    /**
     * Compute line-by-line diff between two text strings.
     *
     * @return array<int, array{type: string, content: string}>
     */
    public function computeTextDiff(string $oldText, string $newText): array;
}
