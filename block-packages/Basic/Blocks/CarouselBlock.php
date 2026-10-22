<?php

declare(strict_types=1);

namespace BlockPackages\Basic\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;

/**
 * Full-width slideshow.
 *
 * Lives at section level (it manages its own interior layout), never inside a
 * column: {@see isFullWidth()} keeps the editor from nesting it.
 */
class CarouselBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'slides.*.title', 'slides.*.subtitle', 'slides.*.cta_text'];
    }

    public function getType(): string
    {
        return 'carousel';
    }

    public function getName(): string
    {
        return 'Carrusel';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-images';
    }

    public function getCategory(): string
    {
        return 'media';
    }

    public function getDescription(): string
    {
        return 'Presentación de diapositivas a pantalla completa con imagen, titular, texto y llamada a la acción.';
    }

    public function isFullWidth(): bool
    {
        return true;
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => '',
            'height' => '520',
            'autoplay' => true,
            'interval' => 6000,
            'show_arrows' => true,
            'show_dots' => true,
            'slides' => [
                [
                    'image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1920&q=80',
                    'title' => 'Infraestructura preparada para agentes de IA',
                    'subtitle' => 'Publica en varios mercados e idiomas desde una sola base de datos.',
                    'cta_text' => 'Ver la plataforma',
                    'cta_url' => '/',
                ],
                [
                    'image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1920&q=80',
                    'title' => 'Rendimiento medido en milisegundos',
                    'subtitle' => 'Caché perimetral y páginas servidas por debajo de 50 ms.',
                    'cta_text' => 'Explorar módulos',
                    'cta_url' => '/',
                ],
            ],
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        /** @var array<int, array<string, mixed>> $slides */
        $slides = array_values(array_filter((array) ($payload['slides'] ?? []), static fn ($slide): bool => is_array($slide)));

        if ($slides === []) {
            return '';
        }

        foreach ($slides as &$slide) {
            $url = (string) ($slide['cta_url'] ?? '');
            if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && app()->bound(LocalizationServiceInterface::class)) {
                $slide['cta_url'] = app(LocalizationServiceInterface::class)->url($url, $market, $locale);
            }
        }
        unset($slide);

        // Themes may override the markup with `themes/{theme}/cms/carousel`.
        $view = app(FrontViewResolver::class)->block('cms.carousel') ?? 'components.cms.carousel';

        return view($view, [
            'slides' => $slides,
            'title' => (string) ($payload['title'] ?? ''),
            'height' => max(240, min(900, (int) ($payload['height'] ?? 520))),
            'autoplay' => filter_var($payload['autoplay'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'interval' => max(2000, min(20000, (int) ($payload['interval'] ?? 6000))),
            'showArrows' => filter_var($payload['show_arrows'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'showDots' => filter_var($payload['show_dots'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ])->render();
    }
}
