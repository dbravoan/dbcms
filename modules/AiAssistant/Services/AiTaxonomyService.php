<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Content\EntitySchema;

/**
 * Resolves the entities the AI assistant can operate on.
 *
 * The assistant owns no entity knowledge: it reads the schemas declared by the
 * active modules (via the core registry) and applies the optional
 * `ai_taxonomy.supported` allow-list. Installing/removing a module therefore
 * adds/removes its AI capabilities with zero code changes here.
 */
final class AiTaxonomyService
{
    public function __construct(
        private readonly EntitySchemaRegistryInterface $schemas
    ) {}

    /**
     * @return array<string, EntitySchema>
     */
    public function entities(): array
    {
        $all = $this->schemas->all();
        $supported = config('ai_taxonomy.supported');

        if (! is_array($supported) || $supported === []) {
            return $all;
        }

        return array_filter(
            $all,
            static fn (EntitySchema $schema): bool => in_array($schema->type, $supported, true)
        );
    }

    public function schema(string $type): ?EntitySchema
    {
        return $this->entities()[$type] ?? null;
    }

    public function supports(string $type): bool
    {
        return $this->schema($type) !== null;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->entities());
    }

    public function hint(string $type): string
    {
        $hints = (array) config('ai_taxonomy.hints', []);

        return (string) ($hints[$type] ?? '');
    }

    /**
     * Resolve the entity schema for a create/edit view (or route) name.
     */
    public function entityForView(?string $viewName): ?EntitySchema
    {
        if ($viewName === null || $viewName === '') {
            return null;
        }

        foreach ($this->entities() as $schema) {
            if ($schema->createView === $viewName || $schema->editView === $viewName) {
                return $schema;
            }
        }

        return null;
    }
}
