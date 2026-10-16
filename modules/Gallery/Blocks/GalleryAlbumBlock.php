<?php

declare(strict_types=1);

namespace Modules\Gallery\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use App\Core\Sections\SectionLayout;
use App\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Models\GalleryItem;

class GalleryAlbumBlock extends AbstractBlockType implements TranslatableBlockInterface
{
    public function getType(): string
    {
        return 'gallery';
    }

    public function getName(): string
    {
        return 'Galería de Imágenes / Álbumes';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-images';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDescription(): string
    {
        return 'Muestra un álbum específico de imágenes o un catálogo de galerías fotográficas.';
    }

    public function getRequiredModule(): ?string
    {
        return 'gallery';
    }

    /**
     * The block manages its own grid: it is added as a section, never nested
     * inside a column (a grid inside a column renders as a grid inside a grid).
     */
    public function isFullWidth(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function getTranslationPaths(): array
    {
        return ['title'];
    }

    public function getEditorData(): array
    {
        $galleries = Gallery::where('is_active', true)->with(['translations', 'items'])->get();

        return [
            'gallery_albums' => $galleries->map(fn (Gallery $gallery): array => ['id' => $gallery->id, 'title' => $gallery->title, 'slug' => $gallery->slug, 'cover_url' => $gallery->items->first()?->image_url, 'image_count' => $gallery->items->count()])->all(),
            'gallery_images' => $galleries->flatMap(fn (Gallery $gallery) => $gallery->items->map(fn (GalleryItem $item): array => ['id' => $item->id, 'url' => $item->image_url, 'title' => $item->title, 'gallery_id' => $gallery->id]))->values()->all(),
        ];
    }

    public function getEditorSchema(): array
    {
        $fields = parent::getEditorSchema();
        $fields['album_slug'] = ['type' => 'select', 'label' => 'Galería', 'options' => ['' => 'Todas'] + array_column($this->getEditorData()['gallery_albums'], 'title', 'slug')];

        return $fields;
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Álbumes y Galerías',
            'album_slug' => '',
            'grid_columns' => '3',
            'limit' => 6,
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $title = htmlspecialchars((string) ($payload['title'] ?? 'Galerías'));
        $albumSlug = (string) ($payload['album_slug'] ?? '');
        $columns = SectionLayout::gridColumns($payload, '3');
        $limit = (int) ($payload['limit'] ?? 6);

        $locService = app()->bound(LocalizationServiceInterface::class) ? app(LocalizationServiceInterface::class) : null;

        $market ??= app()->bound('current.market') ? app('current.market') : app(PublicContentDiscoveryInterface::class)->markets()->firstWhere('is_default', true);
        if (! $market instanceof Market) {
            return '';
        }
        /** @var Builder<Gallery> $public */
        $public = app(PublicContentDiscoveryInterface::class)->query('gallery', $market, $locale, ['items']);

        // If an album slug is specified, render images of that album
        if (! empty($albumSlug)) {
            $gallery = (clone $public)->where(fn ($query) => $query->where('id', is_numeric($albumSlug) ? (int) $albumSlug : 0)->orWhereHas('translations', fn ($translation) => $translation->where('locale', $locale)->where('slug', $albumSlug)))
                ->first();

            if ($gallery) {
                $items = $gallery->items->take($limit)->map(function ($item) {
                    return [
                        'title' => $item->title ?? '',
                        'summary' => $item->caption ?? $item->alt_text ?? '',
                        'image' => $item->image_url ?: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600',
                        'badge' => 'Foto',
                        'url' => $item->image_url,
                    ];
                })->toArray();

                $cardsHtml = view('components.cms.card-grid', [
                    'items' => $items,
                    'columns' => $columns,
                    'mapping' => [
                        'title' => 'title',
                        'summary' => 'summary',
                        'image' => 'image',
                        'badge' => 'badge',
                        'url' => 'url',
                    ],
                    'style' => 'cms-card',
                ])->render();

                return <<<HTML
                <div class="space-y-6 w-full font-mono">
                    <div class="pb-3 border-b cms-border">
                        <h2 class="text-xl font-bold cms-text flex items-center gap-2">
                            <span class="cms-accent">&gt;</span> {$title}
                        </h2>
                    </div>
                    {$cardsHtml}
                </div>
                HTML;
            }
        }

        // Default: Render list of galleries
        $galleries = (clone $public)
            ->take($limit)
            ->get();

        $items = $galleries->map(function ($gallery) use ($locale, $market) {
            $cover = $gallery->items->first();
            $trans = $gallery->translations->where('locale', $locale)->first() ?? null;
            $gTitle = $trans->title ?? ($gallery->title ?? 'Galería');
            $gSlug = $trans->slug ?? ($gallery->slug ?? (string) $gallery->id);
            $url = app(PublicContentDiscoveryInterface::class)->url('gallery', $market, $locale, $gSlug);

            return [
                'title' => $gTitle,
                'summary' => $trans->description ?? $gallery->description ?? '',
                'image' => $cover?->image_url ?: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600',
                'badge' => $gallery->items->count().' fotos',
                'url' => $url,
                'cta_label' => 'Ver álbum',
            ];
        })->toArray();

        $cardsHtml = view('components.cms.card-grid', [
            'items' => $items,
            'columns' => $columns,
            'mapping' => [
                'title' => 'title',
                'summary' => 'summary',
                'image' => 'image',
                'badge' => 'badge',
                'url' => 'url',
                'cta_label' => 'cta_label',
            ],
            'style' => 'cms-card',
        ])->render();

        return <<<HTML
        <div class="space-y-6 w-full font-mono">
            <div class="pb-3 border-b cms-border">
                <h2 class="text-xl font-bold cms-text flex items-center gap-2">
                    <span class="cms-accent">&gt;</span> {$title}
                </h2>
            </div>
            {$cardsHtml}
        </div>
        HTML;
    }
}
