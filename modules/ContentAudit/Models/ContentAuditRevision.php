<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\ContentAudit\Enums\AuditAction;

/**
 * @property int $id
 * @property int|null $content_audit_id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property int|null $user_id
 * @property int $version
 * @property string $action
 * @property string|null $status_before
 * @property string|null $status_after
 * @property string $title
 * @property string|null $summary
 * @property array<string, mixed> $snapshot
 * @property array<string, mixed>|null $diff
 * @property string|null $notes
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read ContentAudit|null $audit
 * @property-read Model $auditable
 * @property-read User|null $user
 */
class ContentAuditRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * Metadata needed by revision lists. The snapshot/diff JSON columns are
     * intentionally excluded: loading and sorting megabytes per row overflows
     * MySQL's sort buffer on the global changelog.
     *
     * @var list<string>
     */
    public const LIST_COLUMNS = [
        'id',
        'auditable_type',
        'auditable_id',
        'user_id',
        'version',
        'action',
        'title',
        'notes',
        'created_at',
    ];

    protected $table = 'content_audit_revisions';

    protected $fillable = [
        'content_audit_id',
        'auditable_type',
        'auditable_id',
        'user_id',
        'version',
        'action',
        'status_before',
        'status_after',
        'title',
        'summary',
        'snapshot',
        'diff',
        'notes',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'diff' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ContentAudit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(ContentAudit::class, 'content_audit_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActionEnum(): AuditAction
    {
        return AuditAction::tryFrom($this->action) ?? AuditAction::UPDATED;
    }
}
