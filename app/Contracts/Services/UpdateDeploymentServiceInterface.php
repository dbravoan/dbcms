<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdateDeploymentServiceInterface
{
    public function isManaged(string $root): bool;
}
