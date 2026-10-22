<?php

declare(strict_types=1);

namespace Modules\Gallery\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Content\Contracts\PublicSemanticContributorInterface;
use App\Models\Market;
use Modules\Gallery\Models\Gallery;

final class GalleryPublicSemanticContributor implements PublicSemanticContributorInterface
{
    public function key(): string
    {
        return 'gallery';
    }

    public function ownerModule(): string
    {
        return 'gallery';
    }

    public function index(Market $market, string $locale): string
    {
        $base = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
        $galPrefix = app(LocalizationServiceInterface::class)->getModulePrefix('gallery', $locale);
        try {
            $galleries = app(PublicContentDiscoveryInterface::class)->query('gallery', $market, $locale, ['translations', 'items'])
                ->get();

            if ($galleries->isEmpty()) {
                return '';
            }

            $output = "## Media Albums & Galleries\n";
            foreach ($galleries as $gallery) {
                /** @var Gallery $gallery */
                $trans = $gallery->translations->where('locale', $locale)->first();
                if ($trans) {
                    $desc = ! empty($trans->description) ? $trans->description : 'Galería multimedia interactiva.';
                    $output .= "- [{$trans->title}]({$base}/{$galPrefix}/{$trans->slug}): {$desc} ({$gallery->items->count()} elementos)\n";
                }
            }

            return $output."\n";
        } catch (\Throwable) {
            return '';
        }
    }

    public function corpus(Market $market, string $locale): string
    {
        $output = '';
        foreach (app(PublicContentDiscoveryInterface::class)->query('gallery', $market, $locale, ['items'])->get() as $gallery) {
            /** @var Gallery $gallery */
            $translation = $gallery->translations->firstWhere('locale', $locale);
            if ($translation === null) {
                continue;
            }
            $output .= "## Gallery: {$translation->title}\n";
            $output .= '**Source**: '.app(PublicContentDiscoveryInterface::class)->url('gallery', $market, $locale, (string) $translation->slug)."\n\n";
            $output .= strip_tags((string) $translation->description)."\n\n";
            foreach ($gallery->items as $item) {
                $output .= '- '.strip_tags((string) ($item->title ?: $item->alt_text)).': '.strip_tags((string) $item->caption)."\n";
            }
            $output .= "\n---\n\n";
        }

        return $output;
    }
}
