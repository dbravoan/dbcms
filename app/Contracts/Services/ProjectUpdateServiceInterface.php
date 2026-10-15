<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\ProjectUpdatePlan;

interface ProjectUpdateServiceInterface
{
    public function prepare(string $root, string $version): ProjectUpdatePlan;

    public function review(string $root): void;

    public function reconcile(string $root): void;
}
