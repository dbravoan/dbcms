<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

use App\DTOs\BaseDTO;

readonly class AdminNavigationSection extends BaseDTO
{
    /**
     * @param  array<int, AdminNavigationItem>  $items
     */
    public function __construct(
        public string $title,
        public array $items = [],
        public bool $visible = true
    ) {}
}
