<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdateManagedReconcilerServiceInterface
{
    /** @return array<string, mixed> */
    public function reconcile(string $runId): array;
}
