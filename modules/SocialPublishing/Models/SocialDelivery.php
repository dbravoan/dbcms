<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

use Illuminate\Support\Carbon;

/**
 * @property string $publication_id
 * @property string $revision_id
 * @property string $installation_id
 * @property string $status
 * @property string $step
 * @property bool $cancel_requested
 * @property string|null $claim
 * @property string|null $remote_id
 * @property string|null $error_code
 * @property array<string,mixed> $receipt
 * @property int $attempts
 * @property Carbon $due_at
 * @property Carbon|null $lease_until
 * @property array<string,mixed> $scheduler_actor
 * @property Carbon|null $scheduled_at
 */
final class SocialDelivery extends SocialRecord
{
    protected $table = 'sp_deliveries';

    protected $casts = ['cancel_requested' => 'boolean', 'receipt' => 'encrypted:array', 'due_at' => 'datetime', 'lease_until' => 'datetime', 'attempts' => 'integer', 'scheduler_actor' => 'array', 'scheduled_at' => 'datetime'];

    protected $hidden = ['receipt', 'scheduler_actor'];
}
