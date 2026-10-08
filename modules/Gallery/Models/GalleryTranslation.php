<?php

declare(strict_types=1);

namespace Modules\Gallery\Models;

use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gallery_id
 * @property string $locale
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $schema_type
 * @property string|null $meta_description
 * @property int|null $market_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gallery $gallery
 * @property-read Market|null $market
 */
class GalleryTranslation extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'locale',
        'title',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'schema_type',
        'market_id',
    ];

    /**
     * @return BelongsTo<Gallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
