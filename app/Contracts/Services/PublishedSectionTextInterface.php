<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Market;
use Illuminate\Database\Eloquent\Model;

interface PublishedSectionTextInterface
{
    public function text(Model $entity, Market $market, string $locale, bool $includeWidgets = true): string;
}
