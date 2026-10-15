<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ProjectProfile
{
    /** @param list<string> $themes */
    public function __construct(
        public string $name,
        public string $brand,
        public array $themes,
        public string $defaultTheme,
        public string $coreVersion,
        public string $coreSha,
        public string $deployment,
    ) {}

    /** @return array{schema: int, name: string, brand: string, themes: list<string>, default_theme: string, deployment: string, core: array{repository: string, version: string, sha: string}} */
    public function toArray(): array
    {
        return ['schema' => 1, 'name' => $this->name, 'brand' => $this->brand, 'themes' => $this->themes, 'default_theme' => $this->defaultTheme, 'deployment' => $this->deployment, 'core' => ['repository' => 'dbravoan/dbcms', 'version' => $this->coreVersion, 'sha' => $this->coreSha]];
    }
}
