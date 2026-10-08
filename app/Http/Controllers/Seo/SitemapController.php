<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Cms\PageCacheService;
use App\Services\Seo\SitemapIndexService;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

/**
 * Segmented, cacheable sitemap endpoints + dynamic robots.txt.
 *
 * Crawlers hit these constantly, so both are cached with the page-cache version
 * (a content edit regenerates them on the next request).
 */
final class SitemapController extends Controller
{
    public function __construct(
        private readonly SitemapIndexService $sitemaps,
        private readonly PageCacheService $pageCache
    ) {}

    /**
     * Sitemap index (/sitemap.xml).
     */
    public function index(): Response
    {
        $xml = $this->remember('index', fn (): string => $this->sitemaps->indexXml());

        if ($xml === null || $xml === '') {
            $xml = $this->sitemaps->indexXml();
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * One sitemap segment (/sitemaps/{segment}.xml).
     */
    public function segment(string $segment): Response
    {
        if (! preg_match('/^[a-z]+(?:-[1-9][0-9]*)?$/D', $segment)) {
            abort(404);
        }

        if (! $this->sitemaps->hasSegment($segment)) {
            abort(404);
        }

        $xml = $this->remember($segment, fn (): string => (string) $this->sitemaps->segmentXml($segment));

        if ($xml === null || $xml === '') {
            $xml = (string) $this->sitemaps->segmentXml($segment);
        }

        if ($xml === '') {
            abort(404);
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Dynamic robots.txt pointing at the sitemap index and llms.txt.
     */
    public function robots(): Response
    {
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        $isProduction = App::isProduction();

        $lines = ['User-agent: *'];

        if (! $isProduction) {
            // Staging/preview deployments must never be indexed.
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Disallow: /'.trim((string) config('cms.admin_path', 'admin'), '/').'/';
            $lines[] = 'Disallow: /install';
            $lines[] = 'Disallow: /api/';
            $lines[] = 'Allow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$base.'/sitemap.xml';
        $lines[] = 'LLMs: '.$base.'/llms.txt';

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function remember(string $segment, callable $callback): ?string
    {
        try {
            /** @var string|null $value */
            $value = Cache::remember(
                sprintf('cms:sitemap-doc:%s:%s', $this->pageCache->visibilityVersion(), $segment),
                $this->pageCache->ttl(),
                Closure::fromCallable($callback)
            );

            return is_string($value) && $value !== '' ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
