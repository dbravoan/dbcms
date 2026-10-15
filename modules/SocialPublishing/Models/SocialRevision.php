<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

/**
 * @property string $publication_id
 * @property int $number
 * @property array<string,mixed> $package
 * @property array<string,mixed> $metadata
 * @property string $account_identity
 * @property int $account_generation
 * @property string $payload_hash
 */
final class SocialRevision extends SocialRecord
{
    protected $table = 'sp_revisions';

    protected $casts = ['package' => 'array', 'metadata' => 'array', 'number' => 'integer', 'account_generation' => 'integer'];

    protected $hidden = [];
}
