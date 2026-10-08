<?php

declare(strict_types=1);

namespace App\Http\Controllers\Geo;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class GeoPreferencePostController extends Controller
{
    public function __invoke(Request $request, LocalizationServiceInterface $localization): RedirectResponse
    {
        $marketCode = (string) $request->input('market', 'es');
        $localeCode = (string) $request->input('locale', 'es');
        $targetUrl = (string) $request->input('target_url', '');

        $market = Market::where('code', $marketCode)->where('is_active', true)->first();
        $language = Language::where('code', $localeCode)->where('is_active', true)->first();

        if (empty($targetUrl)) {
            $targetUrl = $localization->url('/', $market, $language);
        }

        return redirect($targetUrl)->cookie(
            'cms_geo_preference',
            "{$marketCode}_{$localeCode}",
            60 * 24 * 365,
            '/',
            null,
            false,
            false
        );
    }
}
