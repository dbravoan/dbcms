<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class EntityTranslationRequest
{
    /**
     * @param  list<int>  $marketIds
     * @param  array<string,string>  $fields
     * @param  list<array<string,mixed>>  $sections
     */
    public function __construct(public string $entityType, public string $sourceLocale, public string $targetLocale, public array $marketIds, public array $fields, public array $sections, public ?int $entityId = null, public ?string $provider = null, public ?string $model = null) {}

    /** @param array<string,mixed> $params */
    public static function fromArray(array $params): self
    {
        return new self((string) ($params['entity_type'] ?? ''), (string) ($params['source_locale'] ?? ''), (string) ($params['target_locale'] ?? ''), array_values((array) ($params['editor_market_ids'] ?? [])), (array) ($params['fields'] ?? []), array_values((array) ($params['sections'] ?? [])), isset($params['entity_id']) ? (int) $params['entity_id'] : null, isset($params['text_provider']) ? (string) $params['text_provider'] : (isset($params['provider']) ? (string) $params['provider'] : null), isset($params['text_model']) ? (string) $params['text_model'] : (isset($params['model']) ? (string) $params['model'] : null));
    }
}
