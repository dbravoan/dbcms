<?php

declare(strict_types=1);

namespace Modules\Shop\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use App\Core\Sections\SectionLayout;
use App\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Services\ShopDomainService;

class ShopProductsBlock extends AbstractBlockType implements TranslatableBlockInterface
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
        return ['title'];
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
        $fields = parent::getEditorSchema();
        $data = $this->getEditorData();
        $fields['category'] = ['type' => 'select', 'label' => 'Categoría', 'options' => ['' => 'Todas'] + array_column($data['shop_categories'], 'name', 'slug')];

        return $fields;
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Productos Destacados',
            'category' => '',
            'product_id' => '',
            'limit' => 4,
            'grid_columns' => '4',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $title = htmlspecialchars((string) ($payload['title'] ?? 'Productos Destacados'));
        $limit = (int) ($payload['limit'] ?? 4);
        $category = (string) ($payload['category'] ?? '');
        $specificProductId = (string) ($payload['product_id'] ?? '');
        $columns = SectionLayout::gridColumns($payload, '4');

        $market ??= app()->bound('current.market') ? app('current.market') : app(PublicContentDiscoveryInterface::class)->markets()->firstWhere('is_default', true);
        if (! $market instanceof Market) {
            return '';
        }
        /** @var Builder<Product> $query */
        $query = app(PublicContentDiscoveryInterface::class)->query('product', $market, $locale, ['category.translations']);

        if (! empty($specificProductId)) {
            $query->where('id', $specificProductId);
        } elseif (! empty($category)) {
            $query->whereHas('category', function ($q) use ($category): void {
                $q->where('is_active', true)->where(fn ($selected) => $selected->where('id', $category)->orWhereHas('translations', fn ($tr) => $tr->where('slug', $category)));
            });
        }

        $products = $query->take($limit)->get();

        $locService = app()->bound(LocalizationServiceInterface::class) ? app(LocalizationServiceInterface::class) : null;

        $items = $products->map(function ($p) use ($locale, $market, $locService) {
            $trans = $p->translations->where('locale', $locale)->first();
            $slug = $trans->slug ?? (string) $p->id;
            $url = $locService ? $locService->url("/shop/{$slug}", $market, $locale) : url("/shop/{$slug}");
            $priceFormatted = number_format((float) $p->price, 2, ',', '.').' €';

            return [
                'title' => $trans->name ?? 'Producto',
                'summary' => $trans->summary ?? '',
                'image' => $p->featured_image ?: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600',
                'badge' => app(ShopDomainService::class)->shouldShowPrices() ? $priceFormatted : '',
                'url' => $url,
                'cta_label' => 'Ver detalles',
            ];
        })->toArray();

        $allUrl = $locService ? $locService->url('/shop', $market, $locale) : url('/shop');
        $headerHtml = <<<HTML
        <div class="flex items-center justify-between pb-3 border-b cms-border font-mono mb-6">
            <h2 class="text-2xl font-bold cms-text flex items-center gap-2"><span class="cms-accent">&gt;</span> {$title}</h2>
            <a href="{$allUrl}" class="text-xs cms-accent hover:underline">Ir a la tienda &rarr;</a>
        </div>
        HTML;

        $cardsHtml = view('components.cms.card-grid', [
            'items' => $items,
            'columns' => $columns,
            'mapping' => [
                'title' => 'title',
                'summary' => 'summary',
                'image' => 'image',
                'badge' => 'badge',
                'url' => 'url',
                'cta_label' => 'cta_label',
            ],
            'style' => 'cms-card',
        ])->render();

        return <<<HTML
        <div class="space-y-6 w-full font-mono">
            {$headerHtml}
            {$cardsHtml}
        </div>
        HTML;
    }
}
