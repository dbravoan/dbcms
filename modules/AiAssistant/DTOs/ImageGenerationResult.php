<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class ImageGenerationResult
{
    public function __construct(
        public string $url,
        public string $provider,
        public string $model,
        public ?string $binaryData = null,
        public string $mimeType = 'image/webp',
        public ?int $width = null,
        public ?int $height = null,
        public ?int $seed = null,
        public ?string $prompt = null,
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
            'width' => $this->width,
            'height' => $this->height,
            'seed' => $this->seed,
            'prompt' => $this->prompt,
            'metadata' => $this->metadata,
        ];
    }
}
