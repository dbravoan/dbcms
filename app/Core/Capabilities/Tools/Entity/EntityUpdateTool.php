<?php

declare(strict_types=1);

namespace App\Core\Capabilities\Tools\Entity;

use App\Core\Capabilities\Tools\AbstractCapabilityTool;
use App\Services\Capabilities\EntityCrudService;

/**
 * Updates an entity and/or its translations.
 */
class EntityUpdateTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly EntityCrudService $entities,
    ) {}

    public function name(): string
    {
        return 'cms_entity_update';
    }

    public function description(): string
    {
        return 'MCP: updates an entity of any installed type. Send only what changes: "attributes" patches the model (including "publish": true/false) and "translations" patches each locale (fields you omit are kept). New locales are created automatically with a unique slug';
    }

    public function properties(): array
    {
        return [
            'type' => ['type' => 'string', 'description' => 'Entity type'],
            'id' => ['type' => ['integer', 'string'], 'description' => 'Entity id'],
            'attributes' => ['type' => 'object', 'description' => 'Model attributes to patch'],
            'translations' => ['type' => 'object', 'description' => 'locale → fields to patch (or add)'],
            'market_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'audit_action' => ['type' => 'string', 'enum' => ['draft', 'submit_for_review'], 'description' => 'Save a private draft or submit it to editorial review; never grants publication authority'],
            'audit_notes' => ['type' => 'string', 'description' => 'Optional editorial review notes'],
            'sections_json' => ['type' => ['object', 'array', 'string'], 'description' => 'Draft block sections as a locale-keyed object (es/en), a flat list, or a JSON string encoding either shape'],
            'publish' => ['type' => 'boolean', 'description' => 'Requires human approval of the current draft'],
        ];
    }

    public function required(): array
    {
        return ['type', 'id'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->safePermission(fn (): ?string => $this->entities->schemaFor($this->string($args, 'type'))->permission);
    }

    public function ability(): string
    {
        return self::ABILITY_WRITE;
    }

    public function handle(array $args): array
    {
        return $this->entities->update(
            $this->string($args, 'type'),
            $args['id'] ?? 0,
            $args,
        );
    }
}
