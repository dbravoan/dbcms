<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/** Identifies the exported official development snapshot without trusting local Git metadata. */
interface ProjectSourceSnapshotRepositoryInterface
{
    /** @return array{sha:string,files:array<string,string>} */
    public function snapshot(string $directory, ?string $root = null): array;
}
