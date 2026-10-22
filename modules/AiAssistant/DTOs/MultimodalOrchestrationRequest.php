<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class MultimodalOrchestrationRequest
{
    public function __construct(
        public string $topic,
        public string $intent = 'informative',
        public string $entityType = 'post',
        public string $market = 'es',
        public string $locale = 'es',
        public string $style = 'periodistico',
        public bool $generateImage = true,
        public bool $generateVideo = false,
        public bool $researchWeb = false,
        public bool $persistDraft = false,
        public ?string $textProvider = null,
        public ?string $imageProvider = null,
        public ?string $videoProvider = null,
        public ?int $targetCategoryId = null,
        /** @var array<string, mixed> */
        public array $options = [],
    ) {}
}
