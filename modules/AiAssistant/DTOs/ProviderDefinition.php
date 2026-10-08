<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

/**
 * Immutable descriptor for an AI provider shown in the backoffice hub.
 */
final readonly class ProviderDefinition
{
    public function __construct(
        public string $id,
        public string $label,
        public string $description,
        public string $icon = 'fa-solid fa-cube',
        public string $badge = '',
        public string $badgeColor = '',
        public ?string $docUrl = null,
        public ?string $defaultBaseUrl = null,
        public bool $supportsModelListing = true,
        public bool $requiresApiKey = true,
        /** @var array<string, string> Map of model source (e.g. zen/go) to base URL. */
        public array $sourceBaseUrls = [],
        /** @var list<string> Modalities supported: 'text', 'image', 'video' */
        public array $modalities = ['text'],
    ) {}

    public function supportsModality(string $modality): bool
    {
        return in_array($modality, $this->modalities, true);
    }
}
