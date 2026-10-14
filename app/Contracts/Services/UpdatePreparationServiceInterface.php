<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdatePreparationServiceInterface
{
    /** @return array<string, mixed> */
    public function prepare(string $version, int $actorId): array;

    /** @return array<string, mixed> */
    public function adopt(string $version, string $distribution, int $actorId): array;

    public function proposal(string $runId, int $actorId): string;
}
