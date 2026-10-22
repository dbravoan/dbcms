<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

final class CheckoutPolicy
{
    public static function paymentMethod(string $method): string
    {
        if (! in_array($method, ['bank_transfer', 'cod'], true)) {
            throw new \InvalidArgumentException('Only offline payment methods are available.');
        }

        return $method;
    }
}
