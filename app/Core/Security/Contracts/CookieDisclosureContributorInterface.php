<?php

declare(strict_types=1);

namespace App\Core\Security\Contracts;

interface CookieDisclosureContributorInterface
{
    /** @return array<string, array{key: string, title: string, description: string, required: bool, default: bool, cookies: array<int, array{name: string, provider: string, purpose: string, duration: string}>}> */
    public function categories(string $locale): array;
}
