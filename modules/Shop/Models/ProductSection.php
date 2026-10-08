<?php

declare(strict_types=1);

namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $type
 * @property string|null $widget_key
 * @property int $order
 * @property array<string, mixed>|null $styling_json
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Collection<int, ProductSectionTranslation> $translations
 */
class ProductSection extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'widget_key',
        'order',
        'styling_json',
        'is_active',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'styling_json' => 'array',
            'is_active' => 'boolean',
            'order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductSectionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductSectionTranslation::class);
    }
}
