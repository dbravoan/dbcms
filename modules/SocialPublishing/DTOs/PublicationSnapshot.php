<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\DTOs;

use App\DTOs\Marketing\CreativePackage;

final readonly class PublicationSnapshot
{
    /** @param array<string,mixed> $metadata */
    public function __construct(public CreativePackage $package, public string $accountIdentity, public int $accountGeneration, public array $metadata, public int $version = 1) {}

    public function hash(): string
    {
        return hash('sha256', json_encode([$this->package->fingerprint(), $this->accountIdentity, $this->accountGeneration, self::canonical($this->metadata), $this->version], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonical($item);
        }

        return $value;
    }
}
