<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\DTOs\Seo\SeoMetadataDTO;
use Illuminate\Support\Facades\Config;

class OpenGraphService
{
    /**
     * Resolves meta and OpenGraph tags into a clean associative array.
     *
     * @return array<string, string>
     */
    public function resolveMetaTags(SeoMetadataDTO $dto): array
    {
        $siteName = is_string($v = Config::get('seo.defaults.title')) ? $v : (string) Config::get('cms.name', 'CMS');
        $configuredOgImage = function_exists('setting') ? (string) setting('general.og_image', '') : '';
        $image = SeoImageUrlResolver::resolve(
            $dto->ogImage ?: $configuredOgImage,
            is_string($v = Config::get('seo.defaults.og_image')) ? $v : null
        );
        $twitterHandle = is_string($v = Config::get('seo.defaults.twitter_handle')) ? $v : '@dbcms';

        $tags = [
            'title' => "{$dto->title} | {$siteName}",
            'description' => $dto->description,
            'canonical' => $dto->canonicalUrl,
            'og:title' => $dto->title,
            'og:description' => $dto->description,
            'og:url' => $dto->canonicalUrl,
            'og:type' => 'article',
            'og:site_name' => $siteName,
            'twitter:card' => $image !== null ? 'summary_large_image' : 'summary',
            'twitter:site' => $twitterHandle,
            'twitter:title' => $dto->title,
            'twitter:description' => $dto->description,
        ];
        if ($image !== null) {
            $tags['og:image'] = $image;
            $tags['twitter:image'] = $image;
        }

        return $tags;
    }
}
