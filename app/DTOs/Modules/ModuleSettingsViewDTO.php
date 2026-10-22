<?php

declare(strict_types=1);

namespace App\DTOs\Modules;

final readonly class ModuleSettingsViewDTO
{
    /** @param view-string $view
     * @param  array<string, mixed>  $data
     */
    public function __construct(public string $view, public array $data) {}
}
