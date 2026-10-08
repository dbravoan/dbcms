<?php

declare(strict_types=1);

namespace App\Core\Content;

/**
 * Declarative description of a content entity owned by a module.
 *
 * Modules register their schemas so generic consumers (the AI assistant, MCP
 * bridge, importers, auditors...) know which entities exist, which semantic
 * fields they expose and how each one maps to a form input — without any of
 * those consumers hardcoding knowledge about the module.
 *
 * `fields` maps a *semantic role* (title, summary, body, meta_title...) to the
 * form input that carries it. The `{locale}` placeholder is resolved at runtime
 * for localized forms.
 */
final readonly class EntitySchema
{
    public const ROLE_TITLE = 'title';

    public const ROLE_SLUG = 'slug';

    public const ROLE_SUMMARY = 'summary';

    public const ROLE_BODY = 'body';

    public const ROLE_META_TITLE = 'meta_title';

    public const ROLE_META_DESCRIPTION = 'meta_description';

    /**
     * @param  array<string,mixed>  $creationDefaults
     * @param  array<string, array{input: string, label: string, required?: bool, multiline?: bool}>  $fields
     */
    public function __construct(
        public string $type,
        public string $label,
        public string $model,
        public bool $sectionable = false,
        public string $description = '',
        public array $fields = [],
        public ?string $createView = null,
        public ?string $editView = null,
        public bool $searchable = true,
        public float $searchWeight = 1.0,
        public ?string $searchPrefix = null,
        public ?string $urlTemplate = null,
        public ?string $permission = null,
        public ?string $urlPrefix = null,
        public array $creationDefaults = [],
        public ?string $ownerModule = null,
        public ?string $mutationHandler = null,
        public bool $uniqueSlugs = true,
    ) {}

    /**
     * @return list<string>
     */
    public function fieldRoles(): array
    {
        return array_keys($this->fields);
    }

    public function hasField(string $role): bool
    {
        return isset($this->fields[$role]);
    }
}
