<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\DTOs\ProjectProfile;

final readonly class ProjectBootstrapSession
{
    /**
     * @param  array<string,string>  $baseline
     * @param  array<string,string>  $inventory
     * @param  list<array{path:string,action:string}>  $currentChanges
     */
    public function __construct(
        public string $root,
        public string $stagePath,
        public string $version,
        public string $sha,
        public array $baseline = [],
        public array $inventory = [],
        public array $currentChanges = [],
        public ?ProjectProfile $existingProfile = null,
    ) {}
}
