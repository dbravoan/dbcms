<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface ProjectDeploymentAdoptionServiceInterface
{
    /**
     * @param  array{repository:string,version:string,sha:string}  $core
     * @param  list<string>  $paths
     */
    public function assertRemovable(string $root, array $core, array $paths): void;
}
