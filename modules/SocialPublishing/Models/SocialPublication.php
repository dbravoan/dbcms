<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

/**
 * @property string $account_id
 * @property string|null $revision_id
 * @property string|null $approved_revision_id
 * @property string $payload_hash
 * @property string $installation_id
 * @property int $owner_id
 * @property string $status
 * @property array<string,mixed> $owner_actor
 */
final class SocialPublication extends SocialRecord
{
    protected $table = 'sp_publications';

    protected $casts = ['owner_id' => 'integer', 'owner_actor' => 'array'];

    protected $hidden = ['owner_actor'];
}
