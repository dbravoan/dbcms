<?php

declare(strict_types=1);

namespace Modules\Shop\DTOs;

final readonly class InquirySubmission
{
    public function __construct(
        public int $productId,
        public string $name,
        public string $email,
        public string $message,
        public ?string $phone = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self((int) $data['product_id'], (string) $data['name'], (string) $data['email'], (string) $data['message'], isset($data['phone']) ? (string) $data['phone'] : null);
    }
}
