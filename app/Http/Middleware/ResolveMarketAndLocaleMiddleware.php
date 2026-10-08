<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Models\Market;
use App\Services\Cms\CookieConsentService;
use App\Services\Cms\VisitorGeoDetectionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveMarketAndLocaleMiddleware
{
    public function __construct(
        protected LocalizationServiceInterface $localization,
        protected VisitorGeoDetectionService $geoDetector
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $market = $this->localization->resolveMarket($request);
        $language = $this->localization->resolveLocale($request, $market);

        foreach (['market' => $market->code, 'locale' => $language->code] as $parameter => $resolved) {
            $requested = $request->route($parameter);
            abort_unless(! is_string($requested) || $requested === '' || $requested === $resolved, 404);
        }

        $requestedLocale = $request->route('locale');
        if (is_string($requestedLocale) && $requestedLocale !== '') {
            abort_unless($market->languages()->whereKey($language->getKey())->where('is_active', true)->exists(), 404);
        }

        App::setLocale($language->code);

        // Configure dynamic URL defaults for route helpers
        URL::defaults([
            'market' => $market->code,
            'locale' => $language->code,
            'shopPrefix' => $this->localization->getModulePrefix('shop', $language->code),
            'blogPrefix' => $this->localization->getModulePrefix('blog', $language->code),
        ]);

        // Bind singletons into container for domain services
        app()->instance('current.market', $market);
        app()->instance('current.language', $language);

        // Active markets and languages collections
        $allActiveMarkets = Market::with('languages')->where('is_active', true)->get();
        $marketLanguages = $market->languages()->where('is_active', true)->get();

        $hasMultipleMarkets = $this->localization->hasMultipleMarkets();
        $hasMultipleLanguages = $this->localization->hasMultipleLanguages($market);

        // First-time visitor geo recommendation check
        $geoCookie = $request->cookie('cms_geo_preference');
        $showGeoModal = empty($geoCookie) && ($hasMultipleMarkets || $hasMultipleLanguages);
        $geoRecommendation = $this->geoDetector->getRecommendation($request);

        // First-time visitor cookie consent check
        $cookieConsentService = app(CookieConsentService::class);
        $showCookieBanner = ! $cookieConsentService->hasGivenConsent($request);

        // Build base alternate URLs map for current request
        $alternateUrls = $this->localization->buildAlternateUrlsMap($request);

        // Share with all Blade views and layouts
        View::share('currentMarket', $market);
        View::share('currentLanguage', $language);
        View::share('hasMultipleMarkets', $hasMultipleMarkets);
        View::share('hasMultipleLanguages', $hasMultipleLanguages);
        View::share('allActiveMarkets', $allActiveMarkets);
        View::share('marketLanguages', $marketLanguages);
        View::share('showGeoModal', $showGeoModal);
        View::share('showCookieBanner', $showCookieBanner);
        View::share('recommendedMarket', $geoRecommendation['market']);
        View::share('recommendedLanguage', $geoRecommendation['language']);
        View::share('detectedCountry', $geoRecommendation['country']);
        View::share('alternateUrls', $alternateUrls);

        return $next($request);
    }
}
