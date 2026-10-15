<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

use Illuminate\Support\Carbon;

/**
 * @property string $provider
 * @property string $remote_id
 * @property string $identity
 * @property int $authorization_generation
 * @property string $label
 * @property string $status
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property list<string> $grants
 * @property array<string,mixed> $metadata
 * @property Carbon|null $expires_at
 * @property int|null $connected_by
 */
final class SocialAccount extends SocialRecord
{
    protected $attributes = ['authorization_generation' => 1, 'metadata' => '[]', 'grants' => '[]'];

    protected $table = 'sp_accounts';

    protected $casts = ['grants' => 'array', 'metadata' => 'array', 'authorization_generation' => 'integer', 'access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime'];

    protected $hidden = ['access_token', 'refresh_token'];
}
