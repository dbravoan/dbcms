<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

use Illuminate\Support\Carbon;

/**
 * @property string $delivery_id
 * @property array<string,mixed> $metrics
 * @property string $source
 * @property string $availability
 * @property Carbon $observed_at
 */
final class SocialMetric extends SocialRecord
{
    protected $table = 'sp_metrics';

    protected $casts = ['metrics' => 'array', 'observed_at' => 'datetime'];

    protected $hidden = [];
}
