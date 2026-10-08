<?php

declare(strict_types=1);

namespace Modules\Forms\Models;

use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $custom_form_id
 * @property array<string, mixed> $payload
 * @property string|null $locale
 * @property int|null $market_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $is_reviewed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomForm $form
 */
class FormSubmission extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'payload',
        'locale',
        'market_id',
        'ip_address',
        'user_agent',
        'is_reviewed',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_reviewed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CustomForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
