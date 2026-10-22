<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $campaign_id
 * @property int $user_id
 * @property string $request_key
 * @property string $request_hash
 * @property string $status
 * @property int $reserved_cost
 * @property int|null $confirmed_cost
 * @property array<string,mixed> $payload
 * @property list<array<string,mixed>> $stages
 * @property string|null $revision_id
 * @property string|null $error
 * @property string|null $claim_id
 * @property Carbon|null $lease_until
 */
final class CreativeGenerationTask extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'campaign_id', 'user_id', 'request_key', 'request_hash', 'status', 'reserved_cost', 'confirmed_cost', 'payload', 'stages', 'revision_id', 'lease_until', 'claim_id', 'error'];

    protected $casts = ['payload' => 'encrypted:array', 'stages' => 'encrypted:array', 'reserved_cost' => 'integer', 'confirmed_cost' => 'integer', 'lease_until' => 'datetime'];
}
