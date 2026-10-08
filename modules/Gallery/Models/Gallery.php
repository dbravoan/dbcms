<?php

declare(strict_types=1);

namespace Modules\Gallery\Models;

use App\Core\Content\Contracts\TranslatableEntity;
use App\Core\Traits\MarketScoped;
use App\Core\Traits\Translatable;
use App\Models\Market;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, GalleryItem> $items
 * @property-read Collection<int, GalleryTranslation> $translations
 * @property-read Collection<int, Market> $markets
 */
class Gallery extends Model implements TranslatableEntity
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, MarketScoped, Translatable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GalleryTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(GalleryTranslation::class);
    }

    /**
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_gallery');
    }

    /**
     * @return HasMany<GalleryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('order', 'asc');
    }
}
