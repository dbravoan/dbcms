<?php

declare(strict_types=1);

namespace Modules\Gallery\DTOs;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Models\Market;
use Illuminate\Http\Request;

final readonly class GalleryFrontInput
{
    public function __construct(public Market $market, public string $locale, public string $absoluteUrl, public string $slug = '', public int $page = 1) {}

    public static function fromRequest(Request $request, LocalizationServiceInterface $localization): self
    {
        $market = $localization->resolveMarket($request);

        return new self($market, $localization->resolveLocale($request, $market)->code, $request->url(), (string) $request->route('slug'), max(1, (int) $request->query('page', 1)));
    }
}
