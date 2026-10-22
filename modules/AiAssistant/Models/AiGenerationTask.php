<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Queued AI generation request with its lifecycle state.
 *
 * The backoffice dispatches a task, polls this record and renders the result,
 * so slow local models never block a web request.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string $action
 * @property array<string, mixed> $payload
 * @property string $status
 * @property array<string, mixed>|null $result
 * @property string|null $error
 * @property string|null $provider
 * @property string|null $model
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class AiGenerationTask extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNCERTAIN = 'uncertain';

    public const STATUS_APPROVAL_REQUIRED = 'approval_required';

    public const STATUS_NEEDS_INPUT = 'needs_input';

    protected $table = 'ai_generation_tasks';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'action',
        'payload',
        'status',
        'result',
        'error',
        'provider',
        'model',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_UNCERTAIN, self::STATUS_APPROVAL_REQUIRED, self::STATUS_NEEDS_INPUT], true);
    }
}
