<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

use Modules\AiAssistant\Enums\AiEndpointFamily;

/**
 * Immutable representation of a single selectable model.
 */
final readonly class ModelOption
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $source = null,
        public AiEndpointFamily $family = AiEndpointFamily::ChatCompletions,
        public bool $experimental = false,
    ) {}

    /**
     * @param  array{id?: mixed, name?: mixed, label?: mixed, source?: mixed, family?: mixed, experimental?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            label: (string) ($data['label'] ?? $data['name'] ?? $data['id'] ?? ''),
            source: isset($data['source']) ? (string) $data['source'] : null,
            family: AiEndpointFamily::tryFrom((string) ($data['family'] ?? '')) ?? AiEndpointFamily::ChatCompletions,
            experimental: (bool) ($data['experimental'] ?? false),
        );
    }

    /**
     * @return array{id: string, name: string, source: ?string, family: string, experimental: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->label,
            'source' => $this->source,
            'family' => $this->family->value,
            'experimental' => $this->experimental,
        ];
    }
}
