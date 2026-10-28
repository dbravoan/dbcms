<?php

declare(strict_types=1);

namespace Modules\Shop\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Core\Content\Contracts\PublicWidgetSemanticContributorInterface;
use App\Core\Sections\SectionLayout;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use Illuminate\Database\Eloquent\Builder;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Services\ShopDomainService;

class ShopProductsBlock extends AbstractBlockType implements PublicWidgetSemanticContributorInterface
{
    public function getType(): string
    {
        return 'widget_products';
    }

    public function getName(): string
    {
        return 'Productos de la Tienda';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-bag-shopping';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDescription(): string
    {
        return 'Muestra catálogo de productos, destacados de tienda o selección por categoría comercial.';
    }

    public function getRequiredModule(): ?string
    {
        return 'shop';
    }

    /**
     * The block manages its own grid: it is added as a section, never nested
     * inside a column (a grid inside a column renders as a grid inside a grid).
     */
    public function isFullWidth(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function getTranslationPaths(): array
    {
        return ['title', 'subtitle'];
    }

    public function getEditorData(): array
    {
        return [
            'shop_categories' => ProductCategory::with('translations')->get()->map(fn ($category): array => ['id' => $category->id, 'name' => $category->translations->first()->name ?? (string) $category->id, 'slug' => $category->translations->first()->slug ?? ''])->all(),
            'shop_products' => Product::where('is_active', true)->with('translations')->latest()->limit(20)->get()->map(fn ($product): array => ['id' => $product->id, 'name' => $product->translations->first()->name ?? (string) $product->id, 'slug' => $product->translations->first()->slug ?? '', 'price' => $product->price])->all(),
        ];
    }

    public function getEditorSchema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Título', 'description' => 'Título opcional sobre la lista de productos.'],
            'subtitle' => ['type' => 'textarea', 'label' => 'Subtítulo', 'description' => 'Texto introductorio opcional.'],
            'selection' => ['type' => 'select', 'label' => 'Selección', 'options' => ['all' => 'Catálogo', 'featured' => 'Destacados'], 'default' => 'all', 'description' => 'Filtro aplicado cuando no eliges un producto concreto.', 'show_when' => ['field' => 'product_id', 'falsy' => true]],
            'category' => ['type' => 'select', 'label' => 'Categoría', 'options' => ['' => 'Todas'], 'options_source' => 'shop_categories', 'option_label' => 'name', 'option_value' => 'id', 'rules' => ['nullable', 'integer', 'min:1'], 'description' => 'Limita la lista a una categoría.', 'show_when' => ['field' => 'product_id', 'falsy' => true]],
            'product_id' => ['type' => 'select', 'label' => 'Producto', 'options' => ['' => 'Selección automática'], 'options_source' => 'shop_products', 'option_label' => 'name', 'option_value' => 'id', 'rules' => ['nullable', 'integer', 'min:1'], 'description' => 'Si eliges un producto concreto se ignoran selección, categoría y límite.'],
            'limit' => ['type' => 'integer', 'label' => 'Límite', 'default' => 4, 'rules' => ['nullable', 'integer', 'between:1,50'], 'description' => 'Número máximo de productos.', 'show_when' => ['field' => 'product_id', 'falsy' => true]],
            'grid_columns' => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2', '3', '4'], 'default' => '4', 'description' => 'Columnas de la rejilla en pantallas grandes.'],
        ];
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Productos',
            'subtitle' => '',
            'selection' => 'all',
            'category' => '',
            'product_id' => '',
            'limit' => 4,
            'grid_columns' => '4',
        ];
    }

    public function keys(): array
    {
        return [$this->getType()];
    }

    public function ownerModule(): string
    {
        return 'shop';
    }

    public function text(array $payload, Market $market, string $locale): string
    {
        $text = '';
        $showPrices = app(ShopDomainService::class)->shouldShowPrices();
        foreach ($this->items($payload, $market, $locale) as $item) {
            $text .= '- ['.strip_tags($item['title']).']('.$item['url'].')'.($showPrices ? ': '.$item['price'] : '')."\n";
        }

        return $text;
    }

    /** @param array<string,mixed> $payload
     * @return array<int,array<string,string>>
     */
    private function items(array $payload, Market $market, string $locale): array
    {
        $discovery = app(PublicContentDiscoveryInterface::class);
        /** @var Builder<Product> $query */
        $query = $discovery->query('product', $market, $locale, ['markets']);
        $specific = $payload['product_id'] ?? '';
        $category = (string) ($payload['category'] ?? '');
        if ($specific !== '') {
            $query->whereKey($specific);
        } elseif ($category !== '') {
            $query->whereHas('category', fn ($categories) => $categories->where('is_active', true)->whereKey($category));
        }
        if (($payload['selection'] ?? 'all') === 'featured' && (clone $query)->where('is_featured', true)->exists()) {
            $query->where('is_featured', true);
        }
        $limit = min(50, max(1, (int) ($payload['limit'] ?? 4)));

        return $query->orderBy('id')->limit($limit)->get()->map(function (Product $entity) use ($discovery, $market, $locale): array {
            $translation = $entity->translations->firstWhere('locale', $locale);

            return [
                'title' => (string) $translation?->name,
                'image' => (string) $entity->featured_image,
                'url' => $discovery->url('product', $market, $locale, (string) $translation?->slug),
                'price' => $entity->getFormattedPrice($market),
            ];
        })->all();
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $market ??= app()->bound('current.market') ? app('current.market') : app(PublicContentDiscoveryInterface::class)->markets()->firstWhere('is_default', true);
        if (! $market instanceof Market) {
            return '';
        }
        $view = app(FrontViewResolver::class)->resolve('shop.blocks.products', 'shop::blocks.products');

        return view($view, [
            'items' => $this->items($payload, $market, $locale),
            'title' => (string) ($payload['title'] ?? ''),
            'subtitle' => (string) ($payload['subtitle'] ?? ''),
            'showPrices' => app(ShopDomainService::class)->shouldShowPrices(),
            'gridClass' => match (SectionLayout::gridColumns($payload, '4')) {
                '2' => 'grid-cols-1 md:grid-cols-2', '3' => 'grid-cols-1 md:grid-cols-3', default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4'
            },
            'currentLocale' => $locale,
            'indexUrl' => app(LocalizationServiceInterface::class)->frontUrl('shop', '', $locale, $market),
        ])->render();
    }
}
