<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdateHistoryServiceInterface
{
    /** @return list<array<string, mixed>> */
    public function recent(int $actorId): array;
}
