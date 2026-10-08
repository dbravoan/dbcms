<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use App\Core\Content\Contracts\TranslatableEntity;
use App\Models\Market;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string|null $notification_email
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $name
 * @property-read string|null $description
 * @property-read string $submit_button_text
 * @property-read string|null $success_message
 * @property-read Collection<int, CustomFormTranslation> $translations
 * @property-read Collection<int, FormField> $fields
 * @property-read Collection<int, FormSubmission> $submissions
 */
class CustomForm extends Model implements TranslatableEntity
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'notification_email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CustomFormTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CustomFormTranslation::class);
    }

    /**
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_form');
    }

    /**
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('order', 'asc');
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class)->latest();
    }

    /**
     * Resolve the best translation for a locale/market (exact market → generic → any locale).
     */
    public function translation(?string $locale = null, ?int $marketId = null): ?CustomFormTranslation
    {
        $locale = $locale ?? app()->getLocale();
        $marketId = $marketId ?? self::currentMarketId();

        /** @var Collection<int, CustomFormTranslation> $translations */
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->first(fn (CustomFormTranslation $t): bool => $t->locale === $locale && $t->market_id === $marketId)
            ?? $translations->first(fn (CustomFormTranslation $t): bool => $t->locale === $locale && $t->market_id === null)
            ?? $translations->firstWhere('locale', $locale)
            ?? $translations->first();
    }

    /** @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->translation()->name ?? $this->slug));
    }

    /** @return Attribute<string|null, never> */
    protected function description(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->translation()?->description);
    }

    /** @return Attribute<string, never> */
    protected function submitButtonText(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->translation()->submit_button_text ?? 'Enviar'));
    }

    /** @return Attribute<string|null, never> */
    protected function successMessage(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->translation()?->success_message);
    }

    private static function currentMarketId(): ?int
    {
        if (! app()->bound('current.market')) {
            return null;
        }

        $market = app('current.market');

        return $market instanceof Market ? (int) $market->id : null;
    }
}
