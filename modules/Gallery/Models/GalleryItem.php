<?php

declare(strict_types=1);

namespace Modules\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gallery_id
 * @property string $image_url
 * @property string|null $title
 * @property string|null $alt_text
 * @property string|null $caption
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GalleryItem extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'image_url',
        'title',
        'alt_text',
        'caption',
        'order',
    ];

    /**
     * @return BelongsTo<Gallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
