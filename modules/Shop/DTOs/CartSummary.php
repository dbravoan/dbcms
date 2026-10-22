<?php

declare(strict_types=1);

namespace Modules\Shop\DTOs;

use Modules\Shop\Models\Product;

final readonly class CartSummary
{
    /** @param array<int, array{product: Product, quantity: int, unit_price: float, subtotal: float, formatted_unit_price: string, formatted_subtotal: string}> $items */
    public function __construct(public array $items, public float $subtotal, public float $taxTotal, public float $grandTotal) {}
}
