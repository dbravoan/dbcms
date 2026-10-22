<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Models;

use App\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $mode
 * @property string $objective
 * @property string $audience
 * @property array<string,mixed> $context_snapshot
 * @property array<string,mixed> $brand_snapshot
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int $budget_limit
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string $currency
 */
final class CreativeCampaign extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'mode', 'objective', 'audience', 'context_snapshot', 'brand_snapshot', 'source_type', 'source_id', 'budget_limit', 'currency', 'starts_at', 'ends_at'];

    protected $casts = ['context_snapshot' => 'array', 'brand_snapshot' => 'array', 'source_id' => 'integer', 'budget_limit' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    /** @return HasMany<CreativeCampaignTranslation,$this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CreativeCampaignTranslation::class, 'campaign_id');
    }

    /** @return BelongsToMany<Market,$this> */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'creative_campaign_market', 'campaign_id', 'market_id');
    }

    /** @return HasMany<CreativeRevision,$this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(CreativeRevision::class, 'campaign_id')->orderByDesc('number');
    }
}
