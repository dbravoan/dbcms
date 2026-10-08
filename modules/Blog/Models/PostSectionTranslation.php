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
 * @property int $post_section_id
 * @property string $locale
 * @property array<string, mixed> $content_json
 * @property int|null $market_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PostSection $section
 */
class PostSectionTranslation extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'post_section_id',
        'locale',
        'content_json',
        'market_id',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PostSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(PostSection::class, 'post_section_id');
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
