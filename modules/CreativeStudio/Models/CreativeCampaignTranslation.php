<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $locale
 * @property string $title
 * @property string $brief
 */
final class CreativeCampaignTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'locale', 'title', 'brief'];
}
