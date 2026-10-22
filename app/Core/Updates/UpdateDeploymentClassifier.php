<?php

declare(strict_types=1);

namespace App\Core\Updates;

use App\Contracts\Services\UpdateDeploymentServiceInterface;

final class UpdateDeploymentClassifier implements UpdateDeploymentServiceInterface
{
    public function isManaged(string $root): bool
    {
        return file_exists($root.'/cms-project.json') || file_exists($root.'/.git') || is_link($root) || file_exists('/.dockerenv') || config('updates.execution_model') === 'managed' || (bool) config('updates.managed', false);
    }
}
