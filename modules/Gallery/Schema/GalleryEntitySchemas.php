<?php

declare(strict_types=1);

namespace Modules\Gallery\Schema;

use App\Core\Content\EntitySchema;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Services\GalleryMutationHandler;

/**
 * Declares the Gallery entities a generic consumer may operate on.
 */
final class GalleryEntitySchemas
{
    /**
     * @return list<EntitySchema>
     */
    public static function all(): array
    {
        return [self::gallery()];
    }

    private static function gallery(): EntitySchema
    {
        return new EntitySchema(
            type: 'gallery',
            label: 'Galería',
            model: Gallery::class,
            description: 'Álbum multimedia localizado con título, descripción y metadatos.',
            permission: 'manage:galleries',
            mutationHandler: GalleryMutationHandler::class,
            urlPrefix: 'gallery',
            createView: 'admin.galleries.create',
            editView: 'admin.galleries.edit',
            fields: [
                'schema_type' => ['input' => 'translations[{locale}][schema_type]', 'label' => 'Tipo de schema'],
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][title]', 'label' => 'Título', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Slug'],
                EntitySchema::ROLE_BODY => ['input' => 'translations[{locale}][description]', 'label' => 'Descripción', 'multiline' => true],
                EntitySchema::ROLE_META_DESCRIPTION => ['input' => 'translations[{locale}][meta_description]', 'label' => 'Meta descripción', 'multiline' => true],
                EntitySchema::ROLE_META_TITLE => ['input' => 'translations[{locale}][meta_title]', 'label' => 'Meta título'],
            ],
        );
    }
}
