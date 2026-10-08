<?php

declare(strict_types=1);

namespace Modules\Blog\Models;

use App\Contracts\Sectionable;
use App\Core\Content\Contracts\TranslatableEntity;
use App\Core\Traits\MarketScoped;
use App\Core\Traits\Translatable;
use App\Models\Market;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int $user_id
 * @property int|null $category_id
 * @property string|null $featured_image
 * @property int $reading_time_minutes
 * @property bool $is_featured
 * @property bool $is_published
 * @property bool $allow_comments
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $author
 * @property-read Category|null $category
 * @property-read Collection<int, PostTranslation> $translations
 */
class Post extends Model implements Sectionable, TranslatableEntity
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, MarketScoped, Translatable;

    protected $fillable = [
        'user_id',
        'category_id',
        'featured_image',
        'reading_time_minutes',
        'is_featured',
        'is_published',
        'allow_comments',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'allow_comments' => 'boolean',
            'published_at' => 'datetime',
            'reading_time_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Publishing always stamps a publish date: the front resolves content
        // through `published()` which requires is_published AND published_at.
        static::saving(function (Post $post): void {
            if ($post->is_published && $post->published_at === null) {
                $post->published_at = now();
            }
        });
    }

    public function allowsComments(): bool
    {
        $globalEnabled = (bool) module_setting('blog', 'comments_enabled', true);

        return $globalEnabled && (bool) ($this->allow_comments ?? true);
    }

    /**
     * @return HasMany<PostTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    /**
     * @return HasMany<PostSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PostSection::class)->where('is_active', true)->orderBy('order', 'asc');
    }

    public function sectionModelClass(): string
    {
        return PostSection::class;
    }

    public function sectionTranslationModelClass(): string
    {
        return PostSectionTranslation::class;
    }

    public function sectionForeignKey(): string
    {
        return 'post_id';
    }

    /**
     * @return HasMany<PostSection, $this>
     */
    public function publishedSections(): HasMany
    {
        return $this->hasMany(PostSection::class)
            ->where('is_active', true)
            ->where('status', 'published')
            ->orderBy('order', 'asc');
    }

    /**
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_post');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('is_approved', true);
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->published()->where('is_featured', true);
    }
}
