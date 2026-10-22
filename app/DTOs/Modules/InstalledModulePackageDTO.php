<?php

declare(strict_types=1);

namespace App\DTOs\Modules;

final readonly class InstalledModulePackageDTO
{
    /** @param array<string, mixed> $manifest */
    public function __construct(public string $directory, public array $manifest) {}
}
