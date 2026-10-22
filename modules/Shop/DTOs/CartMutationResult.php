<?php

declare(strict_types=1);

namespace Modules\Shop\DTOs;

final readonly class CartMutationResult
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data, public bool $accepted = true) {}
}
