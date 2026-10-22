<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdateMigrationServiceInterface
{
    /**
     * @param  array<string,mixed>  $manifest
     * @return list<string>
     */
    public function pending(array $manifest): array;

    /** @param list<string> $paths */
    public function apply(array $paths): void;
}
