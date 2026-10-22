<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

/**
 * @property string $delivery_id
 * @property string $operation_key
 * @property string $step
 * @property string $status
 * @property bool $mutation
 * @property string|null $error_code
 * @property array<string,mixed> $receipt
 */
final class SocialAttempt extends SocialRecord
{
    protected $table = 'sp_attempts';

    protected $casts = ['receipt' => 'encrypted:array', 'mutation' => 'boolean'];

    protected $hidden = ['receipt'];
}
