<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\Tools\AbstractCapabilityTool;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;

/** Enforces the entity permission/scopes as well as the common AI availability. */
final class EntityTranslationCapability extends AbstractCapabilityTool
{
    public function __construct(private readonly CallableCapability $capability, private readonly EntitySchemaRegistryInterface $entities) {}

    public function name(): string
    {
        return $this->capability->name();
    }

    public function description(): string
    {
        return $this->capability->description();
    }

    public function properties(): array
    {
        return $this->capability->properties();
    }

    public function required(): array
    {
        return $this->capability->required();
    }

    public function permission(): string
    {
        return 'use:ai';
    }

    public function permissionFor(array $args): string
    {
        if (! isset($args['entity_type'])) {
            return $this->permission();
        }
        $type = is_string($args['entity_type']) ? $args['entity_type'] : '';

        $schema = $this->entities->get($type);

        return $schema === null ? 'access:admin' : ($schema->permission ?? 'access:admin');
    }

    public function ability(): string
    {
        return self::ABILITY_WRITE;
    }

    public function handle(array $args): array
    {
        return $this->capability->handle($args);
    }
}
