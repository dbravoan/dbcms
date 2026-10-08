<?php

declare(strict_types=1);

namespace App\Core\Capabilities\Tools\Entity;

use App\Core\Capabilities\Tools\AbstractCapabilityTool;
use App\Services\Capabilities\EntityCrudService;

/**
 * Creates an entity of any registered type, translations included.
 */
class EntityCreateTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly EntityCrudService $entities,
    ) {}

    public function name(): string
    {
        return 'cms_entity_create';
    }

    public function description(): string
    {
        return 'MCP: creates an entity of any installed type (post, page, product, category, gallery, form) with one or more translations at once. Attributes are limited to the fields the backoffice form writes; use "publish": true to publish what supports it. Content under editorial review is queued for approval exactly like in the backoffice';
    }

    public function properties(): array
    {
        return [
            'type' => ['type' => 'string', 'description' => 'Entity type: post, page, product, blog_category, shop_category, gallery, form'],
            'attributes' => ['type' => 'object', 'description' => 'Model attributes (e.g. {"category_id": 3, "is_featured": true, "publish": true, "price": 99.9})'],
            'translations' => ['type' => 'object', 'description' => 'locale → fields, e.g. {"es": {"title": "…", "slug": "…", "summary": "…", "content": "…"}, "en": {...}}'],
            'market_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Markets in which the content belongs'],
            'audit_action' => ['type' => 'string', 'enum' => ['draft', 'submit_for_review'], 'description' => 'Save a private draft or submit it to editorial review; never grants publication authority'],
            'audit_notes' => ['type' => 'string', 'description' => 'Optional editorial review notes'],
            'sections_json' => ['type' => ['object', 'array', 'string'], 'description' => 'Complete block sections as a locale-keyed object (es/en), a flat list, or a JSON string encoding either shape'],
            'publish' => ['type' => 'boolean', 'description' => 'Requires human approval; omit to create a draft'],
        ];
    }

    public function required(): array
    {
        return ['type'];
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
        return $this->entities->create(
            $this->string($args, 'type'),
            $args,
            $this->actor(),
        );
    }
}
