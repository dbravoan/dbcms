<?php

declare(strict_types=1);

namespace Modules\Shop\Models;

use App\Core\Content\Contracts\TranslatableEntity;
use App\Core\Traits\MarketScoped;
use App\Models\Market;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string|null $icon
 * @property string|null $image
 * @property int $order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductCategory|null $parent
 * @property-read Collection<int, ProductCategory> $children
 * @property-read Collection<int, ProductCategoryTranslation> $translations
 * @property-read Collection<int, Product> $products
 */
class ProductCategory extends Model implements TranslatableEntity
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use MarketScoped;

    protected $fillable = [
        'parent_id',
        'icon',
        'image',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ProductCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order', 'asc');
    }

    /**
     * @return HasMany<ProductCategoryTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductCategoryTranslation::class, 'category_id');
    }

    /**
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_product_category', 'category_id', 'market_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $translation = $this->translations->where('locale', $locale)->first()
            ?? $this->translations->first();

        return $translation->name ?? 'Categoría #'.$this->id;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $translation = $this->translations->where('locale', $locale)->first()
            ?? $this->translations->first();

        return $translation?->description;
    }
}
