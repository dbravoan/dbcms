<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** @property string $id */
abstract class SocialRecord extends Model
{
    use HasUuids;

    protected $guarded = [];
}
