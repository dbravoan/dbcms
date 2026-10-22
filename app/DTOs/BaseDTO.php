<?php

declare(strict_types=1);

namespace App\DTOs;

abstract readonly class BaseDTO
{
    /**
     * Converts DTO properties to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
