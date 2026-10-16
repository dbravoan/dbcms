<?php

declare(strict_types=1);

namespace Modules\Marketing\DTOs;

use InvalidArgumentException;

final readonly class CreativePackage
{
    /** @param list<ArtifactDescriptor> $artifacts
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public string $packageId,
        public string $revisionId,
        public ?string $campaignId,
        public string $locale,
        public string $market,
        public string $text,
        public ?string $destinationUrl,
        public array $artifacts,
        public array $provenance = [],
        public int $version = 1,
    ) {
        if ($version !== 1 || $packageId === '' || $revisionId === '' || $locale === '' || $market === ''
            || ($text === '' && $artifacts === []) || ! array_is_list($artifacts)
            || count($artifacts) > 30 || strlen($text) > 100000
            || ($destinationUrl !== null && (! filter_var($destinationUrl, FILTER_VALIDATE_URL)
                || ! in_array(parse_url($destinationUrl, PHP_URL_SCHEME), ['http', 'https'], true)
                || parse_url($destinationUrl, PHP_URL_USER) !== null || parse_url($destinationUrl, PHP_URL_PASS) !== null))) {
            throw new InvalidArgumentException('Invalid creative package.');
        }
        foreach ($artifacts as $artifact) {
            if (! $artifact instanceof ArtifactDescriptor) {
                throw new InvalidArgumentException('A package must contain typed immutable artifacts.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_replace(get_object_vars($this), ['artifacts' => array_map(static fn (ArtifactDescriptor $artifact): array => $artifact->toArray(), $this->artifacts)]);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $artifacts = [];
        foreach ((array) ($data['artifacts'] ?? []) as $artifact) {
            if (! is_array($artifact)) {
                throw new InvalidArgumentException('Invalid artifact in package.');
            }
            $artifacts[] = ArtifactDescriptor::fromArray($artifact);
        }

        return new self((string) ($data['packageId'] ?? ''), (string) ($data['revisionId'] ?? ''),
            isset($data['campaignId']) ? (string) $data['campaignId'] : null,
            (string) ($data['locale'] ?? ''), (string) ($data['market'] ?? ''), (string) ($data['text'] ?? ''),
            isset($data['destinationUrl']) ? (string) $data['destinationUrl'] : null, $artifacts,
            (array) ($data['provenance'] ?? []), (int) ($data['version'] ?? 1));
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode(self::canonical($this->toArray()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
