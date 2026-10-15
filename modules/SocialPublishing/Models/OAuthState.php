<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

use Illuminate\Support\Carbon;

/**
 * @property string $state_hash
 * @property string $provider
 * @property int $user_id
 * @property string $session_hash
 * @property string $redirect_uri
 * @property string $verifier
 * @property array<string,mixed> $discovery
 * @property Carbon $expires_at
 */
final class OAuthState extends SocialRecord
{
    protected $table = 'sp_oauth_states';

    protected $casts = ['verifier' => 'encrypted', 'discovery' => 'encrypted:array', 'expires_at' => 'datetime', 'user_id' => 'integer'];

    protected $hidden = ['state_hash', 'verifier', 'discovery'];
}
