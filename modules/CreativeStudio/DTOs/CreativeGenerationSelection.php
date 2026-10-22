<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\DTOs;

final readonly class CreativeGenerationSelection
{
    public function __construct(public string $format, public string $locale, public string $market, public int $slides, public int $duration, public string $template, public string $imageEncoding, public string $instruction) {}

    /** @param array<string,mixed> $data */
    public static function fromValidated(array $data): self
    {
        return new self((string) $data['format'], (string) $data['locale'], (string) $data['market'], (int) ($data['slides'] ?? 3), (int) ($data['duration'] ?? 30), (string) ($data['template'] ?? ($data['format'] === 'video' ? 'video' : 'square')), (string) ($data['image_encoding'] ?? 'png'), (string) ($data['instruction'] ?? ''));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['format' => $this->format, 'locale' => $this->locale, 'market' => $this->market, 'slides' => $this->slides, 'duration' => $this->duration, 'template' => $this->template, 'image_encoding' => $this->imageEncoding, 'instruction' => $this->instruction];
    }
}
