<?php

declare(strict_types=1);

namespace Modules\Shop\DTOs;

/** Created only by the authenticated HTTP adapter, never from a client payload. */
final readonly class OrderAccessContext
{
    /** @param list<string> $sessionOrderNumbers */
    public function __construct(public ?int $userId, public array $sessionOrderNumbers, public bool $validSignature) {}
}
