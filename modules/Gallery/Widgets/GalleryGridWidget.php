<?php

declare(strict_types=1);

namespace Modules\Gallery\Widgets;

use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Widgets\BaseWidgetComponent;
use App\Models\Market;
use Illuminate\Contracts\View\View;

class GalleryGridWidget extends BaseWidgetComponent
{
    public static function getKey(): string
    {
        return 'gallery.grid';
    }

    public static function getTitle(): string
    {
        return 'Filterable Image Gallery Grid';
    }

    public static function getOptionsSchema(): array
    {
        return [
            'gallery_slug' => ['type' => 'string', 'label' => 'Gallery Slug'],
            'columns' => ['type' => 'select', 'options' => [2, 3, 4], 'default' => 3],
        ];
    }

    public function render(array $parameters = []): View|string
    {
        $slug = isset($parameters['gallery_slug']) ? (string) $parameters['gallery_slug'] : 'default';
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->getLocale();
        $gallery = $market instanceof Market
            ? app(PublicContentDiscoveryInterface::class)->query('gallery', $market, $locale, ['items'])->whereHas('translations', fn ($translation) => $translation->where('locale', $locale)->where('slug', $slug))->first()
            : null;

        return $this->renderWidgetView('gallery', 'gallery_grid', [
            'gallery' => $gallery,
            'parameters' => $parameters,
        ]);
    }
}
