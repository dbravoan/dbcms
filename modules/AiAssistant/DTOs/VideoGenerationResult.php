<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class VideoGenerationResult
{
    public function __construct(
        public string $url,
        public string $provider,
        public string $model,
        public ?string $binaryData = null,
        public string $mimeType = 'video/mp4',
        public int $durationSeconds = 5,
        public ?int $width = 1920,
        public ?int $height = 1080,
        public ?string $posterUrl = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'provider' => $this->provider,
            'model' => $this->model,
            'mime_type' => $this->mimeType,
            'duration_seconds' => $this->durationSeconds,
            'width' => $this->width,
            'height' => $this->height,
            'poster_url' => $this->posterUrl,
            'metadata' => $this->metadata,
        ];
    }
}
