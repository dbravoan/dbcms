<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Contracts\Services\PublishedSectionTextInterface;
use App\Core\Content\Contracts\PublicSemanticContributorInterface;
use App\Models\Market;
use Modules\Shop\Models\Product;

final class ShopPublicSemanticContributor implements PublicSemanticContributorInterface
{
    public function key(): string
    {
        return 'shop';
    }

    public function ownerModule(): string
    {
        return 'shop';
    }

    public function index(Market $market, string $locale): string
    {
        $base = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
        $shopSlug = app(LocalizationServiceInterface::class)->getModulePrefix('shop', $locale);
        try {
            $products = app(PublicContentDiscoveryInterface::class)->query('product', $market, $locale, ['translations'])
                ->take(20)
                ->get();

            if ($products->isEmpty()) {
                return $this->categories($market, $locale);
            }

            $output = "## Products Catalog\n";
            foreach ($products as $product) {
                /** @var Product $product */
                $trans = $product->translations->where('locale', $locale)->first();
                if ($trans) {
                    $desc = ! empty($trans->description) ? strip_tags($trans->description) : 'Producto de catálogo.';
                    $price = app(ShopDomainService::class)->shouldShowPrices() ? " | Precio: {$product->getPriceForMarket($market)} {$market->currency}" : '';
                    $output .= "- [{$trans->name}]({$base}/{$shopSlug}/{$trans->slug}): SKU: `{$product->sku}`{$price} | {$desc}\n";
                }
            }

            return $output."\n".$this->categories($market, $locale);
        } catch (\Throwable) {
            return $this->categories($market, $locale);
        }
    }

    public function corpus(Market $market, string $locale): string
    {
        try {
            $output = '';
            $products = app(PublicContentDiscoveryInterface::class)->query('product', $market, $locale, ['translations', 'publishedSections.translations'])
                ->get();

            foreach ($products as $product) {
                /** @var Product $product */
                $trans = $product->translations->where('locale', $locale)->first();
                if (! $trans) {
                    continue;
                }

                $output .= "## Product: {$trans->name}\n";
                $output .= '**Source**: '.app(PublicContentDiscoveryInterface::class)->url('product', $market, $locale, (string) $trans->slug)."\n";
                $price = app(ShopDomainService::class)->shouldShowPrices() ? " | **Price**: {$product->getPriceForMarket($market)} {$market->currency}" : '';
                $output .= "**SKU**: `{$product->sku}`{$price}\n\n";
                if (! empty($trans->description)) {
                    $output .= "### Description\n".strip_tags($trans->description)."\n\n";
                }
                if (! empty($trans->features) && is_array($trans->features)) {
                    $output .= "### Features\n";
                    foreach ($trans->features as $feature) {
                        $output .= '- '.strip_tags((string) $feature)."\n";
                    }
                    $output .= "\n";
                }
                $output .= app(PublishedSectionTextInterface::class)->text($product, $market, $locale);
                $output .= "---\n\n";
            }

            return $output;
        } catch (\Throwable) {
            return '';
        }
    }

    private function categories(Market $market, string $locale): string
    {
        $discovery = app(PublicContentDiscoveryInterface::class);
        $output = "## Topic Taxonomies & Categories\n";
        foreach ($discovery->query('shop_category', $market, $locale)->get() as $category) {
            $translation = $category->getRelation('translations')->firstWhere('locale', $locale);
            $url = $discovery->entityUrl('shop_category', $category, $market, $locale);
            $output .= "- [{$translation->name}]({$url}): {$translation->description}\n";
        }

        return $output."\n";
    }
}
