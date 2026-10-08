<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\ContentAudit\Models\ContentAudit;

class ContentSubmittedForReviewEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $entity,
        public ContentAudit $audit,
        public User $author,
        public ?string $notes = null
    ) {}
}
