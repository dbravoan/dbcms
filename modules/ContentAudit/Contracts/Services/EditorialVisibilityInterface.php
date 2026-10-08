<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Contracts\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\ContentAudit\Models\ContentAuditRevision;

interface EditorialVisibilityInterface
{
    public function canReview(User $user): bool;

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     */
    public function constrain(Builder $query, User $user): void;

    public function authorizeRevision(ContentAuditRevision $revision, User $user): void;
}
