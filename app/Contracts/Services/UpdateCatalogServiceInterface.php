<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdateCatalogServiceInterface
{
    /** @return array<string, mixed> */
    public function status(): array;

    /** @return array<string, mixed> */
    public function check(bool $force = false): array;
}
