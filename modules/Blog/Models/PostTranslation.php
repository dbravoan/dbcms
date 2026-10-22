<?php

declare(strict_types=1);

namespace Modules\Blog\Models;

use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property string $locale
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string $content
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string $schema_type
 * @property int|null $market_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PostTranslation extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'post_id',
        'locale',
        'title',
        'slug',
        'summary',
        'content',
        'meta_title',
        'meta_description',
        'schema_type',
        'market_id',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
