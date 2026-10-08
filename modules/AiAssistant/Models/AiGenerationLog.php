<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $provider
 * @property string|null $model
 * @property string $action
 * @property string $locale
 * @property string|null $market
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $latency_ms
 * @property string $status
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $total_tokens
 * @property-read User|null $user
 */
class AiGenerationLog extends Model
{
    protected $table = 'ai_generation_logs';

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'action',
        'locale',
        'market',
        'prompt_tokens',
        'completion_tokens',
        'latency_ms',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'latency_ms' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Total tokens consumed by the generation (prompt + completion).
     */
    /** @return Attribute<int, never> */
    protected function totalTokens(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) $this->prompt_tokens + (int) $this->completion_tokens,
        );
    }
}
