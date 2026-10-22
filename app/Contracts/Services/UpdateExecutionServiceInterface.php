<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdateExecutionServiceInterface
{
    /** @return array<string, mixed> */
    public function status(string $runId, int $actorId): array;

    /** @param array<string, mixed> $backupEvidence
     * @return array<string, mixed>
     */
    public function approve(string $runId, int $actorId, string $planHash, array $backupEvidence = []): array;

    /** @return array<string, mixed> */
    public function advance(string $runId, int $actorId): array;

    /** @return array<string, mixed> */
    public function cancel(string $runId, int $actorId): array;
}
