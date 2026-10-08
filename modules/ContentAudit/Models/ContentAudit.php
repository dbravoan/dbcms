<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\ContentAudit\Enums\AuditStatus;

/**
 * @property int $id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property int $current_version
 * @property int|null $pending_revision_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $auditable
 * @property-read User|null $submitter
 * @property-read User|null $reviewer
 * @property-read Collection<int, ContentAuditRevision> $revisions
 */
class ContentAudit extends Model
{
    protected $table = 'content_audits';

    protected $attributes = ['pending_revision_id' => null];

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'status',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'notes',
        'current_version',
        'pending_revision_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'current_version' => 'integer',
            'pending_revision_id' => 'integer',
        ];
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
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<ContentAuditRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentAuditRevision::class, 'content_audit_id')->orderByDesc('version');
    }

    /**
     * @param  Builder<ContentAudit>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', AuditStatus::PENDING_REVIEW->value);
    }

    /**
     * @param  Builder<ContentAudit>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', AuditStatus::APPROVED->value);
    }

    /**
     * @param  Builder<ContentAudit>  $query
     */
    public function scopeRejected(Builder $query): void
    {
        $query->where('status', AuditStatus::REJECTED->value);
    }

    public function getStatusEnum(): AuditStatus
    {
        return AuditStatus::tryFrom($this->status) ?? AuditStatus::DRAFT;
    }
}
