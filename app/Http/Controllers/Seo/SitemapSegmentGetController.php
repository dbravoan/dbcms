<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\PublicDocumentCache;
use App\Services\Seo\SitemapIndexService;
use Illuminate\Http\Response;

final class SitemapSegmentGetController extends Controller
{
    public function __invoke(string $segment, SitemapIndexService $service, PublicDocumentCache $cache): Response
    {
        $xml = $cache->remember('sitemap:segment:'.$segment, fn (): string => $service->segmentXml($segment) ?? '');
        abort_if($xml === '', 404);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
