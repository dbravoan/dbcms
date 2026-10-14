<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdateBackupServiceInterface
{
    /**
     * @param  array<string,mixed>  $evidence
     * @return array<string,mixed>
     */
    public function create(string $directory, array $evidence = []): array;
}
