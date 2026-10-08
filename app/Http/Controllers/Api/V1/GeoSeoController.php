<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\GeoServiceInterface;
use App\Contracts\Services\SeoServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Services\Cms\PageCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class GeoSeoController extends Controller
{
    public function __construct(
        private readonly PageCacheService $pageCache,
    ) {}

    public function llmsTxt(GeoServiceInterface $service): Response
    {
        [$market, $locale] = $this->context();

        return $this->cachedText(
            "llms:{$market?->code}:{$locale}",
            fn (): string => $service->generateLlmsTxt($market, $locale)
        );
    }

    public function llmsFullTxt(GeoServiceInterface $service): Response
    {
        [$market, $locale] = $this->context();

        return $this->cachedText(
            "llms-full:{$market?->code}:{$locale}",
            fn (): string => $service->generateLlmsFullTxt($market, $locale)
        );
    }

    public function sitemapXml(SeoServiceInterface $service): Response
    {
        [$market, $locale] = $this->context();

        return $this->cachedXml(
            "sitemap:{$market?->code}:{$locale}",
            fn (): string => $service->generateSitemapXml($market, $locale)
        );
    }

    public function siteSchema(SeoServiceInterface $service): JsonResponse
    {
        return response()->json($service->generateSiteSchema());
    }

    public function sitemapPart(SeoServiceInterface $service): Response
    {
        [$market, $locale] = $this->context();
        $part = (int) request()->route('part');
        $xml = $service->generateSitemapXml($market, $locale, $part);
        abort_if($xml === '', 404);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Current market/locale context resolved by the middleware.
     *
     * @return array{0: Market|null, 1: string}
     */
    private function context(): array
    {
        $market = App::bound('current.market') ? App::make('current.market') : null;
        $locale = App::bound('current.locale') ? (string) App::make('current.locale') : App::getLocale();

        return [$market, $locale];
    }

    /**
     * Caches a plain-text GEO document with the page-cache namespace version.
     */
    private function cachedText(string $key, callable $generator): Response
    {
        return response($this->remember($key, $generator), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    /**
     * Caches an XML document with the page-cache namespace version.
     */
    private function cachedXml(string $key, callable $generator): Response
    {
        return response($this->remember($key, $generator), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    /**
     * Cached generation: this content changes only when content changes, so it is
     * keyed by the page-cache version and invalidated by the same observers.
     */
    private function remember(string $key, callable $generator): string
    {
        try {
            return (string) Cache::remember(
                sprintf('cms:geo:%s:%s', $this->pageCache->visibilityVersion(), $key),
                $this->pageCache->ttl(),
                \Closure::fromCallable($generator)
            );
        } catch (\Throwable) {
            return (string) $generator();
        }
    }
}
