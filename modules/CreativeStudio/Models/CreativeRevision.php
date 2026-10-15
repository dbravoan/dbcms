<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $campaign_id
 * @property int $user_id
 * @property int $number
 * @property string $locale
 * @property string $market
 * @property string $format
 * @property string $text
 * @property string|null $destination_url
 * @property list<array<string,mixed>> $artifacts
 * @property array<string,mixed> $context_snapshot
 * @property array<string,mixed> $brand_snapshot
 * @property array<string,mixed> $template_snapshot
 * @property array<string,mixed> $provenance
 */
final class CreativeRevision extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'campaign_id', 'user_id', 'number', 'locale', 'market', 'format', 'text', 'destination_url', 'artifacts', 'context_snapshot', 'brand_snapshot', 'template_snapshot', 'provenance'];

    protected $casts = ['number' => 'integer', 'artifacts' => 'array', 'context_snapshot' => 'array', 'brand_snapshot' => 'array', 'template_snapshot' => 'array', 'provenance' => 'array'];
}
