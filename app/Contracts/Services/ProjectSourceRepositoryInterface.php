<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface ProjectSourceRepositoryInterface
{
    public function fetch(string $version, string $sha, string $directory): void;

    public function read(string $directory, string $sha, string $path): string;
}
