<?php

declare(strict_types=1);

namespace App\DTOs;

use ArrayAccess;

/**
 * @implements ArrayAccess<string, mixed>
 */
abstract readonly class BaseDTO implements ArrayAccess
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

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, (string) $offset) && isset($this->{(string) $offset});
    }

    public function offsetGet(mixed $offset): mixed
    {
        return property_exists($this, (string) $offset) ? $this->{(string) $offset} : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('BaseDTO instances are immutable and do not allow array mutation.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('BaseDTO instances are immutable and do not allow array unsetting.');
    }
}
