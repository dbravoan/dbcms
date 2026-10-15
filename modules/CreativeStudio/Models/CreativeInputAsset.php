<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $campaign_id
 * @property int $user_id
 * @property array<string,mixed> $artifact
 */
final class CreativeInputAsset extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'campaign_id', 'user_id', 'artifact'];

    protected $casts = ['artifact' => 'array'];
}
