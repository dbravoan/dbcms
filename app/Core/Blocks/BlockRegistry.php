<?php

declare(strict_types=1);

namespace App\Core\Blocks;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\BlockTypeInterface;
use App\Core\Modules\ModuleManager;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Forms\Models\CustomForm;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Models\GalleryItem;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

class BlockRegistry implements BlockRegistryInterface
{
    /**
     * @var array<string, BlockTypeInterface>
     */
    protected array $blocks = [];

    public function __construct(
        protected ModuleManager $moduleManager
    ) {}

    public function register(BlockTypeInterface $block): void
    {
        $this->blocks[$block->getType()] = $block;
    }

    public function get(string $type): ?BlockTypeInterface
    {
        if (isset($this->blocks[$type])) {
            return $this->blocks[$type];
        }

        $aliasMap = [
            'markdown' => 'text',
            'html' => 'text',
            'widget_stats' => 'kpi',
            'stats' => 'kpi',
            'posts' => 'widget_posts',
            'products' => 'widget_products',
            'form' => 'widget_form',
            'lead_form' => 'widget_form',
        ];

        if (isset($aliasMap[$type]) && isset($this->blocks[$aliasMap[$type]])) {
            return $this->blocks[$aliasMap[$type]];
        }

        return null;
    }

    /**
     * @return array<string, BlockTypeInterface>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * @return array<string, BlockTypeInterface>
     */
    public function getActiveBlocks(): array
    {
        $active = [];

        foreach ($this->blocks as $type => $block) {
            $required = $block->getRequiredModule();

            if ($required === null || $this->moduleManager->isActive($required)) {
                $active[$type] = $block;
            }
        }

        return $active;
    }

    /**
     * @return array<string, array<string, BlockTypeInterface>>
     */
    public function getBlocksByCategory(): array
    {
        $grouped = [];

        foreach ($this->getActiveBlocks() as $type => $block) {
            $category = $block->getCategory();
            $grouped[$category][$type] = $block;
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDynamicDataPayloads(): array
    {
        $payloads = [
            'forms' => [],
            'blog_categories' => [],
            'blog_posts' => [],
            'shop_categories' => [],
            'shop_products' => [],
            'gallery_albums' => [],
            'gallery_images' => [],
            'charts_types' => [
                ['key' => 'roi', 'name' => 'Calculadora & Retorno de Inversión (ROI)'],
                ['key' => 'bar', 'name' => 'Gráfica de Barras Comparativa'],
                ['key' => 'line', 'name' => 'Gráfica de Línea de Crecimiento'],
                ['key' => 'donut', 'name' => 'Gráfica Circular / Anillo Radial'],
                ['key' => 'metric', 'name' => 'Contador Numérico & Métricas'],
            ],
        ];

        // 1. Forms
        try {
            if ($this->moduleManager->isActive('forms') && class_exists(CustomForm::class) && Schema::hasTable('custom_forms')) {
                $forms = CustomForm::where('is_active', true)->with(['translations', 'markets', 'fields.translations'])->get();
                $payloads['forms'] = $forms->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'slug' => $f->slug,
                    'submit_button_text' => $f->submit_button_text,
                    'fields' => $f->fields->map(fn ($field) => [
                        'key' => $field->field_key,
                        'label' => $field->label,
                        'type' => $field->type,
                        'required' => (bool) $field->is_required,
                    ])->toArray(),
                ])->toArray();
            }
        } catch (\Throwable) {
            // Graceful fallback during tests or migrations
        }

        // 2. Blog Categories & Posts
        try {
            if ($this->moduleManager->isActive('blog') && class_exists(Category::class) && Schema::hasTable('categories')) {
                $categories = Category::with('translations')->get();
                $payloads['blog_categories'] = $categories->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->translations->first()->name ?? "Categoría #{$c->id}",
                    'slug' => $c->translations->first()->slug ?? (string) $c->id,
                ])->toArray();
            }

            if ($this->moduleManager->isActive('blog') && class_exists(Post::class) && Schema::hasTable('posts')) {
                $posts = Post::where('is_published', true)->with('translations')->latest()->take(20)->get();
                $payloads['blog_posts'] = $posts->map(fn ($p) => [
                    'id' => $p->id,
                    'title' => $p->translations->first()->title ?? "Artículo #{$p->id}",
                    'slug' => $p->translations->first()->slug ?? (string) $p->id,
                ])->toArray();
            }
        } catch (\Throwable) {
            // Graceful fallback
        }

        // 3. Shop Categories & Products
        try {
            if ($this->moduleManager->isActive('shop') && class_exists(ProductCategory::class) && Schema::hasTable('product_categories')) {
                $shopCats = ProductCategory::with('translations')->get();
                $payloads['shop_categories'] = $shopCats->map(fn ($sc) => [
                    'id' => $sc->id,
                    'name' => $sc->translations->first()->name ?? "Categoría #{$sc->id}",
                    'slug' => $sc->translations->first()->slug ?? (string) $sc->id,
                ])->toArray();
            }

            if ($this->moduleManager->isActive('shop') && class_exists(Product::class) && Schema::hasTable('products')) {
                $products = Product::where('is_active', true)->with('translations')->latest()->take(20)->get();
                $payloads['shop_products'] = $products->map(fn ($pr) => [
                    'id' => $pr->id,
                    'name' => $pr->translations->first()->name ?? "Producto #{$pr->id}",
                    'slug' => $pr->translations->first()->slug ?? (string) $pr->id,
                    'price' => $pr->price,
                ])->toArray();
            }
        } catch (\Throwable) {
            // Graceful fallback
        }

        // 4. Galleries
        try {
            if ($this->moduleManager->isActive('gallery') && class_exists(Gallery::class) && Schema::hasTable('galleries')) {
                $galleries = Gallery::where('is_active', true)->with('items')->get();
                $payloads['gallery_albums'] = $galleries->map(fn ($g) => [
                    'id' => $g->id,
                    'title' => $g->title,
                    'slug' => $g->slug,
                    'cover_url' => $g->items->first()?->image_url,
                    'image_count' => $g->items->count(),
                ])->toArray();

                // Sample real image URLs available for block imagery.
                if (class_exists(GalleryItem::class) && Schema::hasTable('gallery_items')) {
                    $payloads['gallery_images'] = GalleryItem::query()
                        ->whereIn('gallery_id', $galleries->pluck('id'))
                        ->whereNotNull('image_url')
                        ->orderBy('order')
                        ->take(12)
                        ->get()
                        ->map(fn ($item) => [
                            'url' => (string) $item->image_url,
                            'alt' => (string) ($item->alt_text ?: $item->title),
                        ])
                        ->values()
                        ->toArray();
                }
            }
        } catch (\Throwable) {
            // Graceful fallback
        }

        return $payloads;
    }
}
