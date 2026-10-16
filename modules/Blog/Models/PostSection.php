<?php

declare(strict_types=1);

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property string $type
 * @property int $order
 * @property array<string, mixed>|null $styling_json
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 * @property-read Collection<int, PostSectionTranslation> $translations
 */
class PostSection extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'post_id',
        'type',
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
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return HasMany<PostSectionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(PostSectionTranslation::class);
    }
}
