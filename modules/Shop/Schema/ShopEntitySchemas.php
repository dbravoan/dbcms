<?php

declare(strict_types=1);

namespace Modules\Shop\Schema;

use App\Core\Content\EntitySchema;
use App\Core\Content\PublicContentDefinition;
use Illuminate\Support\Str;
use Modules\Shop\Models\Inquiry;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Services\ProductMutationHandler;
use Modules\Shop\Services\ShopCategoryMutationHandler;

/**
 * Declares the Shop entities a generic consumer may operate on.
 */
final class ShopEntitySchemas
{
    /**
     * @return list<EntitySchema>
     */
    public static function all(): array
    {
        return [self::product(), self::category(), new EntitySchema(
            type: 'shop_inquiry', label: 'Solicitud de presupuesto', model: Inquiry::class,
            description: 'Contacto comercial recibido del catálogo, con estado de revisión.',
            permission: 'manage:shop', searchable: false,
            fields: [EntitySchema::ROLE_TITLE => ['input' => 'name', 'label' => 'Nombre'], EntitySchema::ROLE_BODY => ['input' => 'message', 'label' => 'Mensaje', 'multiline' => true]],
        )];
    }

    private static function product(): EntitySchema
    {
        return new EntitySchema(
            type: 'product',
            publicContent: new PublicContentDefinition('products', 'fa-solid fa-bag-shopping', includeHub: true),
            mutationHandler: ProductMutationHandler::class,
            label: 'Producto',
            model: Product::class,
            sectionable: true,
            description: 'Producto de tienda con resumen, maquetación por bloques y metadatos SEO.',
            permission: 'manage:shop',
            urlPrefix: 'shop',
            createView: 'admin.shop.products.create',
            adminFormView: 'admin.shop.products._form',
            editView: 'admin.shop.products.edit',
            creationDefaults: ['sku' => static fn (): string => 'DRAFT-'.Str::uuid(), 'price' => 0, 'stock_quantity' => 0],
            fields: [
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][name]', 'label' => 'Nombre', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Slug'],
                EntitySchema::ROLE_SUMMARY => ['input' => 'translations[{locale}][summary]', 'label' => 'Resumen', 'multiline' => true],
                EntitySchema::ROLE_BODY => ['input' => 'translations[{locale}][description]', 'label' => 'Descripción', 'multiline' => true],
                EntitySchema::ROLE_META_TITLE => ['input' => 'translations[{locale}][meta_title]', 'label' => 'Meta título'],
                EntitySchema::ROLE_META_DESCRIPTION => ['input' => 'translations[{locale}][meta_description]', 'label' => 'Meta descripción', 'multiline' => true],
                'schema_type' => ['input' => 'translations[{locale}][schema_type]', 'label' => 'Schema.org'],
            ],
        );
    }

    private static function category(): EntitySchema
    {
        return new EntitySchema(
            type: 'shop_category',
            publicContent: new PublicContentDefinition('categories', 'fa-solid fa-tags'),
            mutationHandler: ShopCategoryMutationHandler::class,
            label: 'Categoría de tienda',
            model: ProductCategory::class,
            description: 'Categoría para clasificar los productos de la tienda.',
            permission: 'manage:shop',
            urlPrefix: 'shop',
            fields: [
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][name]', 'label' => 'Nombre', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Slug'],
                EntitySchema::ROLE_BODY => ['input' => 'translations[{locale}][description]', 'label' => 'Descripción', 'multiline' => true],
            ],
        );
    }
}
