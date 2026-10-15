<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

/**
 * @property string $revision_id
 * @property string $payload_hash
 * @property int $approved_by
 * @property string $account_identity
 * @property int $account_generation
 * @property string $origin
 */
final class SocialApproval extends SocialRecord
{
    protected $table = 'sp_approvals';

    protected $casts = ['approved_by' => 'integer', 'account_generation' => 'integer'];

    protected $hidden = [];
}
