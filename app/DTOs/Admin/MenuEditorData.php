<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

final readonly class MenuEditorData
{
    /** @param array<string, mixed> $viewData */
    public function __construct(public array $viewData) {}
}
