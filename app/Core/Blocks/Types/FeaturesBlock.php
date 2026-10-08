<?php

declare(strict_types=1);

namespace App\Core\Blocks\Types;

use App\Core\Blocks\AbstractBlockType;
use App\Core\Sections\SectionLayout;
use App\Models\Market;

class FeaturesBlock extends AbstractBlockType
{
    public function getType(): string
    {
        return 'features';
    }

    public function getName(): string
    {
        return 'Cuadrícula de Características';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-bolt';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getDescription(): string
    {
        return 'Tarjetas modulares para destacar funcionalidades, capacidades técnicas o ventajas clave.';
    }

    /**
     * The block manages its own grid: it is added as a section, never nested
     * inside a column (a grid inside a column renders as a grid inside a grid).
     */
    public function isFullWidth(): bool
    {
        return true;
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Capacidades de la Plataforma',
            'grid_columns' => '3',
            'items' => [
                ['icon' => '⚡', 'title' => 'Cero Latencia', 'description' => 'Caché perimetral distribuida que sirve páginas en menos de 50ms.'],
                ['icon' => '🛡️', 'title' => 'Seguridad Blindada', 'description' => 'Autenticación Passport OAuth2 con tokens cifrados y protección anti-spam.'],
                ['icon' => '🤖', 'title' => 'Indexación GEO & IA', 'description' => 'Optimización de visibilidad en ChatGPT, Perplexity y motores generativos.'],
            ],
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $rawItems = (array) ($payload['items'] ?? []);
        $items = array_map(function ($item) {
            $item = (array) $item;
            $item['description'] = $item['description'] ?? ($item['desc'] ?? '');

            return $item;
        }, $rawItems);

        $columns = SectionLayout::gridColumns($payload, '3');

        $headerHtml = ! empty($title) ? "<div class=\"text-center max-w-2xl mx-auto mb-10\"><h2 class=\"text-2xl sm:text-3xl font-black cms-text flex items-center justify-center gap-2\"><span class=\"cms-accent\">&gt;_</span> {$title}</h2></div>" : '';

        $cardsHtml = view('components.cms.card-grid', [
            'items' => $items,
            'columns' => $columns,
            'mapping' => [
                'title' => 'title',
                'subtitle' => 'description',
                'icon' => 'icon',
            ],
            'style' => 'cms-card',
        ])->render();

        return <<<HTML
        <div class="space-y-6 w-full font-mono">
            {$headerHtml}
            {$cardsHtml}
        </div>
        HTML;
    }
}
