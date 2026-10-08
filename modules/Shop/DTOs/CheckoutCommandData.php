<?php

declare(strict_types=1);

namespace Modules\Shop\DTOs;

final readonly class CheckoutCommandData
{
    /** @param array<string, string|array<string,string>> $customerData */
    public function __construct(public array $customerData, public string $paymentMethod) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input): self
    {
        $address = array_map(strval(...), array_intersect_key($input, array_flip(['address', 'city', 'postal_code', 'country'])));

        return new self([
            'customer_name' => (string) ($input['customer_name'] ?? ''),
            'customer_email' => (string) ($input['customer_email'] ?? ''),
            'customer_phone' => (string) ($input['customer_phone'] ?? ''),
            'billing_address' => $address, 'shipping_address' => $address,
            'notes' => (string) ($input['notes'] ?? ''),
        ], (string) ($input['payment_method'] ?? ''));
    }
}
