<?php

declare(strict_types=1);

namespace Modules\Blog\Schema;

use App\Core\Content\EntitySchema;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Services\CategoryMutationHandler;
use Modules\Blog\Services\PostMutationHandler;

/**
 * Declares the Blog entities a generic consumer (AI assistant, MCP, importers)
 * may operate on, without those consumers depending on the Blog module.
 */
final class BlogEntitySchemas
{
    /**
     * @return list<EntitySchema>
     */
    public static function all(): array
    {
        return [self::post(), self::category()];
    }

    private static function post(): EntitySchema
    {
        return new EntitySchema(
            type: 'post',
            label: 'Artículo de blog',
            model: Post::class,
            sectionable: true,
            mutationHandler: PostMutationHandler::class,
            description: 'Artículo del blog con resumen, maquetación por bloques y metadatos SEO.',
            permission: 'manage:posts',
            urlPrefix: 'blog',
            createView: 'admin.blog.posts.create',
            editView: 'admin.blog.posts.edit',
            fields: [
                'schema_type' => ['input' => 'translations[{locale}][schema_type]', 'label' => 'Tipo de schema'],
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][title]', 'label' => 'Título', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Slug'],
                EntitySchema::ROLE_SUMMARY => ['input' => 'translations[{locale}][summary]', 'label' => 'Resumen', 'multiline' => true],
                EntitySchema::ROLE_BODY => ['input' => 'translations[{locale}][content]', 'label' => 'Contenido', 'multiline' => true],
                EntitySchema::ROLE_META_TITLE => ['input' => 'translations[{locale}][meta_title]', 'label' => 'Meta título'],
                EntitySchema::ROLE_META_DESCRIPTION => ['input' => 'translations[{locale}][meta_description]', 'label' => 'Meta descripción', 'multiline' => true],
            ],
        );
    }

    private static function category(): EntitySchema
    {
        return new EntitySchema(
            type: 'blog_category',
            uniqueSlugs: false,
            mutationHandler: CategoryMutationHandler::class,
            label: 'Categoría del blog',
            model: Category::class,
            description: 'Categoría para clasificar los artículos del blog.',
            permission: 'manage:posts',
            urlPrefix: 'blog',
            fields: [
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][name]', 'label' => 'Nombre', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Slug'],
                EntitySchema::ROLE_BODY => ['input' => 'translations[{locale}][description]', 'label' => 'Descripción', 'multiline' => true],
            ],
        );
    }
}
