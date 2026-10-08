<?php

declare(strict_types=1);

namespace Modules\Pages\Schema;

use App\Core\Content\EntitySchema;
use Modules\Pages\Models\Page;
use Modules\Pages\Services\PageMutationHandler;

/**
 * Declares the Pages entities a generic consumer may operate on.
 */
final class PageEntitySchemas
{
    /**
     * @return list<EntitySchema>
     */
    public static function all(): array
    {
        return [self::page()];
    }

    private static function page(): EntitySchema
    {
        return new EntitySchema(
            type: 'page',
            label: 'Página',
            model: Page::class,
            sectionable: true,
            mutationHandler: PageMutationHandler::class,
            description: 'Página del sitio compuesta por bloques, con metadatos SEO.',
            permission: 'manage:pages',
            urlPrefix: '',
            createView: 'admin.pages.create',
            editView: 'admin.pages.edit',
            fields: [
                'schema_type' => ['input' => 'translations[{locale}][schema_type]', 'label' => 'Tipo de schema'],
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][title]', 'label' => 'Título', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Slug'],
                EntitySchema::ROLE_META_TITLE => ['input' => 'translations[{locale}][meta_title]', 'label' => 'Meta título'],
                EntitySchema::ROLE_META_DESCRIPTION => ['input' => 'translations[{locale}][meta_description]', 'label' => 'Meta descripción', 'multiline' => true],
            ],
        );
    }
}
