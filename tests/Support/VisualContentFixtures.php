<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Content\Pages\Models\Page;
use App\Core\Sections\SectionSyncService;
use App\Models\Market;
use App\Models\User;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Forms\Models\CustomForm;
use Modules\Gallery\Models\Gallery;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

final class VisualContentFixtures
{
    public static function home(): Page
    {
        $page = CmsFixtures::home();
        app(SectionSyncService::class)->sync($page, json_encode(['es' => [['type' => 'text', 'title' => 'Fixture section', 'content' => 'Published fixture content']]], JSON_THROW_ON_ERROR));

        return $page;
    }

    public static function post(): Post
    {
        $category = Category::create(['is_active' => true]);
        $category->translations()->create(['locale' => 'es', 'name' => 'Noticias', 'slug' => 'noticias']);
        $post = Post::create(['user_id' => User::where('email', 'admin@db-cms.io')->firstOrFail()->id, 'category_id' => $category->id, 'is_published' => true, 'published_at' => now()->subMinute()]);
        $post->translations()->create(['locale' => 'es', 'title' => 'Artículo fixture', 'slug' => 'articulo-fixture', 'summary' => 'Resumen', 'content' => 'Contenido publicado']);
        $post->markets()->sync(Market::pluck('id')->all());

        return $post;
    }

    public static function product(): Product
    {
        $category = ProductCategory::create(['is_active' => true, 'order' => 0]);
        $category->translations()->create(['locale' => 'es', 'name' => 'Productos', 'slug' => 'productos']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'FIXTURE-1', 'price' => 29, 'is_active' => true, 'is_featured' => true]);
        $product->translations()->create(['locale' => 'es', 'name' => 'Producto fixture', 'slug' => 'producto-fixture', 'description' => 'Descripción']);
        $product->markets()->sync(Market::pluck('id')->all());

        return $product;
    }

    public static function gallery(): Gallery
    {
        $gallery = Gallery::create(['title' => 'Proyectos', 'slug' => 'proyectos', 'is_active' => true]);
        $gallery->translations()->create(['locale' => 'es', 'title' => 'Proyectos', 'slug' => 'proyectos', 'description' => 'Galería fixture']);
        $gallery->markets()->sync(Market::pluck('id')->all());
        $gallery->items()->create(['image_url' => 'https://example.test/fixture.jpg', 'title' => 'Imagen fixture', 'alt_text' => 'Proyecto', 'order' => 0]);

        return $gallery;
    }

    public static function form(): CustomForm
    {
        $form = CustomForm::create(['slug' => 'contacto', 'notification_email' => 'admin@example.test', 'is_active' => true]);
        $form->translations()->create(['locale' => 'es', 'name' => 'Contacto', 'description' => 'Formulario fixture', 'submit_button_text' => 'Enviar', 'success_message' => 'Recibido']);
        $form->markets()->sync(Market::pluck('id')->all());
        foreach (['nombre' => 'text', 'email' => 'email', 'empresa' => 'text', 'mensaje' => 'textarea'] as $key => $type) {
            $field = $form->fields()->create(['field_key' => $key, 'type' => $type, 'is_required' => $key !== 'empresa', 'order' => $form->fields()->count()]);
            $field->translations()->create(['locale' => 'es', 'label' => ucfirst($key)]);
        }

        return $form;
    }
}
