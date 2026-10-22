<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\PublicDocumentCache;
use App\Services\Seo\SitemapIndexService;
use Illuminate\Http\Response;

final class SitemapIndexGetController extends Controller
{
    public function __invoke(SitemapIndexService $service, PublicDocumentCache $cache): Response
    {
        $xml = $cache->remember('sitemap:index', fn (): string => $service->indexXml());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
