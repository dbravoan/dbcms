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

    public function getEditorSchema(): array
    {
        $schema = parent::getEditorSchema();
        $schema['title']['description'] = 'Título opcional que se muestra sobre las diapositivas.';
        $schema['height'] = [
            'type' => 'integer',
            'label' => 'Altura',
            'default' => 520,
            'min' => 240,
            'max' => 900,
            'description' => 'Altura del carrusel en píxeles.',
        ];
        $schema['autoplay']['description'] = 'Avanza automáticamente entre las diapositivas.';
        $schema['interval'] = [
            'type' => 'integer',
            'label' => 'Intervalo entre diapositivas',
            'default' => 6000,
            'min' => 2000,
            'max' => 20000,
            'description' => 'Milisegundos entre diapositivas cuando la reproducción automática está activa.',
            'show_when' => ['field' => 'autoplay', 'equals' => true],
        ];
        $schema['show_arrows']['description'] = 'Muestra flechas para navegar manualmente.';
        $schema['show_dots']['description'] = 'Muestra los indicadores de posición.';
        $schema['slides'] = [
            'type' => 'repeater',
            'label' => 'Diapositivas',
            'description' => 'Cada diapositiva puede tener su propia imagen, texto y llamada a la acción.',
            'rules' => ['nullable', 'array'],
            'fields' => [
                'image_url' => ['type' => 'media', 'media_kind' => 'image', 'label' => 'Imagen', 'rules' => ['nullable', 'string', 'max:2048']],
                'title' => ['type' => 'text', 'label' => 'Título de la diapositiva', 'rules' => ['nullable', 'string', 'max:255']],
                'subtitle' => ['type' => 'textarea', 'label' => 'Texto de apoyo', 'rules' => ['nullable', 'string', 'max:1000']],
                'cta_text' => ['type' => 'text', 'label' => 'Texto del botón', 'rules' => ['nullable', 'string', 'max:120']],
                'cta_url' => ['type' => 'url', 'label' => 'Destino del botón', 'description' => 'Ruta interna o URL externa segura.', 'rules' => ['nullable', 'string', 'max:2048']],
            ],
        ];

        return $schema;
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {        /** @var array<int, array<string, mixed>> $slides */
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
