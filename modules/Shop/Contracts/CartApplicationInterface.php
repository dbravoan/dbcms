<?php

declare(strict_types=1);

namespace Modules\Shop\Contracts;

use App\Models\Market;
use Modules\Shop\DTOs\CartMutationResult;

interface CartApplicationInterface
{
    /** @return array<string, mixed> */
    public function read(?Market $market, string $locale): array;

    public function add(int $productId, int $quantity, Market $market): CartMutationResult;

    /** @return array<string, mixed> */
    public function update(int $productId, int $quantity, Market $market): array;

    /** @return array<string, mixed> */
    public function remove(int $productId): array;

    /** @return array<string, mixed> */
    public function clear(): array;
}
