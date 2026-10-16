<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Services\Cms\PageCacheService;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Models\Post;

/**
 * RSS 2.0 feed for the blog, cached with the page-cache version.
 */
final class RssController extends Controller
{
    public function __construct(
        private readonly PageCacheService $pageCache,
        private readonly PublicContentDiscoveryInterface $discovery,
        private readonly LocalizationServiceInterface $localization
    ) {}

    public function __invoke(): Response
    {
        $xml = (string) $this->remember(fn (): string => $this->build());

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
        ]);
    }

    private function build(): string
    {
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        $locale = App::getLocale();
        $siteName = (string) config('cms.name', 'CMS');
        $market = $this->market();

        $posts = [];
        if ($market !== null) {
            $posts = $this->discovery->query('post', $market, $locale)->latest('published_at')->limit(50)->get();
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n";
        $xml .= '  <channel>'."\n";
        $xml .= '    <title>'.htmlspecialchars($siteName, ENT_XML1).'</title>'."\n";
        $xml .= '    <link>'.htmlspecialchars($market !== null ? $this->localization->url('/', $market, $locale) : $base, ENT_XML1).'</link>'."\n";
        $xml .= '    <description>'.htmlspecialchars($siteName, ENT_XML1).'</description>'."\n";
        $xml .= '    <language>'.$locale.'</language>'."\n";
        $xml .= '    <atom:link href="'.htmlspecialchars($base.'/feed.xml', ENT_XML1).'" rel="self" type="application/rss+xml" />'."\n";

        foreach ($posts as $post) {
            /** @var Post $post */
            $translation = $post->translations->where('locale', $locale)->first();

            if ($translation === null || $market === null) {
                continue;
            }

            $url = $this->discovery->url('post', $market, $locale, (string) $translation->slug);

            $xml .= '    <item>'."\n";
            $xml .= '      <title>'.htmlspecialchars((string) $translation->title, ENT_XML1).'</title>'."\n";
            $xml .= '      <link>'.htmlspecialchars($url, ENT_XML1).'</link>'."\n";
            $xml .= '      <guid isPermaLink="true">'.htmlspecialchars($url, ENT_XML1).'</guid>'."\n";
            $xml .= '      <description>'.htmlspecialchars(strip_tags((string) ($translation->summary ?? '')), ENT_XML1).'</description>'."\n";
            if ($post->published_at !== null) {
                $xml .= '      <pubDate>'.$post->published_at->toRfc2822String().'</pubDate>'."\n";
            }
            $xml .= '    </item>'."\n";
        }

        return $xml.'  </channel>'."\n".'</rss>';
    }

    private function market(): ?Market
    {
        $market = request()->attributes->get('market');

        return $market instanceof Market ? $market : $this->discovery->markets()->sortByDesc('is_default')->first();
    }

    private function marketCode(): string
    {
        $market = $this->market();

        return $market === null ? 'none' : (string) $market->code;
    }

    private function remember(callable $callback): string
    {
        try {
            return (string) Cache::remember(
                sprintf('cms:rss:%s:%s:%s', $this->pageCache->visibilityVersion(), $this->marketCode(), App::getLocale()),
                $this->pageCache->ttl(),
                Closure::fromCallable($callback)
            );
        } catch (\Throwable) {
            return $callback();
        }
    }
}
