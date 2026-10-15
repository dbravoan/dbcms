<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

use InvalidArgumentException;

final readonly class ArtifactDescriptor
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public string $path,
        public string $sha256,
        public string $mimeType,
        public int $sizeBytes,
        public ?int $width = null,
        public ?int $height = null,
        public ?float $durationSeconds = null,
        public array $metadata = [],
    ) {
        if ($id === '' || ! preg_match('/^[a-zA-Z0-9_\/-]+\.[a-z0-9]+$/', $path)
            || str_starts_with($path, '/') || str_contains($path, '..')
            || ! preg_match('/^[a-f0-9]{64}$/', $sha256) || $sizeBytes < 1
            || ($width !== null && $width < 1) || ($height !== null && $height < 1)
            || ($durationSeconds !== null && (! is_finite($durationSeconds) || $durationSeconds <= 0))) {
            throw new InvalidArgumentException('Invalid immutable artifact descriptor.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self((string) ($data['id'] ?? ''), (string) ($data['path'] ?? ''), (string) ($data['sha256'] ?? ''),
            (string) ($data['mimeType'] ?? ''), (int) ($data['sizeBytes'] ?? 0),
            isset($data['width']) ? (int) $data['width'] : null, isset($data['height']) ? (int) $data['height'] : null,
            isset($data['durationSeconds']) ? (float) $data['durationSeconds'] : null, (array) ($data['metadata'] ?? []));
    }
}
