<?php

declare(strict_types=1);

namespace Modules\Search\Contracts;

use App\DTOs\Front\FrontPageData;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Support\Collection;
use Modules\Search\DTOs\SearchFrontInput;

interface SearchFrontPageServiceInterface
{
    public function hub(SearchFrontInput $input): FrontPageData;

    public function results(SearchFrontInput $input): FrontPageData;

    /** @return Collection<int,Language> */
    public function languages(Market $market): Collection;
}
