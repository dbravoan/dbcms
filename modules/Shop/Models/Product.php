<?php

declare(strict_types=1);

namespace Modules\Shop\Models;

use App\Contracts\Sectionable;
use App\Core\Content\Contracts\TranslatableEntity;
use App\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $sku
 * @property float $price
 * @property float|null $compare_price
 * @property float|null $cost_price
 * @property int $stock_quantity
 * @property bool $track_stock
 * @property bool $is_digital
 * @property bool $is_featured
 * @property bool $is_active
 * @property string|null $featured_image
 * @property array<int, string>|null $gallery_images
 * @property array<string, mixed>|null $specs_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductCategory|null $category
 * @property-read Collection<int, ProductTranslation> $translations
 * @property-read Collection<int, Market> $markets
 * @property-read Collection<int, ProductSection> $sections
 */
class Product extends Model implements Sectionable, TranslatableEntity
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'price',
        'compare_price',
        'cost_price',
        'stock_quantity',
        'track_stock',
        'is_digital',
        'is_featured',
        'is_active',
        'featured_image',
        'gallery_images',
        'specs_json',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'compare_price' => 'float',
            'cost_price' => 'float',
            'stock_quantity' => 'integer',
            'track_stock' => 'boolean',
            'is_digital' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'gallery_images' => 'array',
            'specs_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ProductTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /**
     * @return BelongsToMany<Market, $this, ProductMarket>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_product')
            ->using(ProductMarket::class)
            ->withPivot('custom_price', 'is_active')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ProductSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ProductSection::class)->where('is_active', true)->orderBy('order', 'asc');
    }

    public function sectionModelClass(): string
    {
        return ProductSection::class;
    }

    public function sectionTranslationModelClass(): string
    {
        return ProductSectionTranslation::class;
    }

    public function sectionForeignKey(): string
    {
        return 'product_id';
    }

    /**
     * @return HasMany<ProductSection, $this>
     */
    public function publishedSections(): HasMany
    {
        return $this->hasMany(ProductSection::class)
            ->where('is_active', true)
            ->where('status', 'published')
            ->orderBy('order', 'asc');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeForMarket(Builder $query, Market $market): Builder
    {
        return $query->where(function (Builder $sub) use ($market): void {
            $sub->whereDoesntHave('markets')
                ->orWhereHas('markets', function (Builder $mQuery) use ($market): void {
                    $mQuery->where('markets.id', $market->id)
                        ->where('market_product.is_active', true);
                });
        });
    }

    /**
     * Gets effective price for a specific market.
     */
    public function getPriceForMarket(?Market $market = null): float
    {
        if ($market && $this->relationLoaded('markets')) {
            $marketPivot = $this->markets->where('id', $market->id)->first();
            $pivot = $marketPivot?->getRelation('pivot');
            if ($pivot instanceof Pivot && $pivot->getAttribute('custom_price') !== null) {
                return (float) $pivot->getAttribute('custom_price');
            }
        }

        return (float) $this->price;
    }

    /**
     * Formats price with currency symbol based on market.
     */
    public function getFormattedPrice(?Market $market = null): string
    {
        $price = $this->getPriceForMarket($market);
        $currency = $market->currency ?? 'EUR';

        return match ($currency) {
            'USD' => '$'.number_format($price, 2),
            'GBP' => '£'.number_format($price, 2),
            'MXN' => '$'.number_format($price, 2).' MXN',
            default => number_format($price, 2, ',', '.').' €',
        };
    }

    public function isInStock(): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        return $this->stock_quantity > 0;
    }
}
