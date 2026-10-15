<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface ProjectMigrationServiceInterface
{
    public function migrate(string $root): void;
}
