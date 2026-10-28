<?php

declare(strict_types=1);

namespace BlockPackages\Basic\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;

class PricingBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'items.*.name', 'items.*.features.*', 'items.*.cta_text', 'items.*.period'];
    }

    public function getType(): string
    {
        return 'pricing';
    }

    public function getName(): string
    {
        return 'Tabla de Planes y Precios';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-gem';
    }

    public function getCategory(): string
    {
        return 'marketing';
    }

    public function getDescription(): string
    {
        return 'Planes de precios comparativos con listas de funcionalidades, precio y botón de suscripción.';
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
            'title' => 'Planes Flexibles para Cada Escala',
            'items' => [
                [
                    'name' => 'Starter',
                    'price' => '29€/mes',
                    'period' => 'Ideal para proyectos en crecimiento',
                    'features' => ['Hasta 5.000 visitas/mes', '1 Mercado activo', 'Soporte estándar por email', 'SSL y CDN perimetral'],
                    'cta_text' => 'Comenzar Starter',
                    'cta_url' => '/contacto',
                ],
                [
                    'name' => 'Pro Enterprise',
                    'price' => '99€/mes',
                    'period' => 'Para marcas globales y alto tráfico',
                    'features' => ['Visitas ilimitadas', 'Multi-Mercado ilimitado', 'SLA 99.9% de uptime', 'Agentes IA y MCP endpoints', 'Soporte prioritario 24/7'],
                    'cta_text' => 'Elegir Plan Pro',
                    'cta_url' => '/contacto',
                ],
            ],
        ];
    }

    public function getEditorSchema(): array
    {
        $schema = parent::getEditorSchema();
        $schema['title']['description'] = 'Título opcional de la comparativa.';
        $schema['items']['label'] = 'Planes';
        $schema['items']['description'] = 'Cada fila es una tarjeta de precio.';
        $schema['items']['fields']['name']['label'] = 'Nombre del plan';
        $schema['items']['fields']['price']['description'] = 'Precio visible, por ejemplo 29 €/mes.';
        $schema['items']['fields']['period']['label'] = 'Descripción del plan';
        $schema['items']['fields']['features']['label'] = 'Características incluidas';
        $schema['items']['fields']['features']['description'] = 'Un elemento por línea.';
        $schema['items']['fields']['cta_text']['label'] = 'Texto del botón';
        $schema['items']['fields']['cta_url']['label'] = 'Destino del botón';

        return $schema;
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $items = (array) ($payload['items'] ?? []);
        $columns = count($items) === 2 ? '2' : (count($items) === 4 ? '4' : '3');

        $headerHtml = ! empty($title) ? "<div class=\"text-center max-w-2xl mx-auto mb-10\"><h2 class=\"text-2xl sm:text-3xl font-black cms-text flex items-center justify-center gap-2\"><span class=\"cms-accent\">&gt;_</span> {$title}</h2></div>" : '';

        foreach ($items as &$item) {
            $url = (string) ($item['cta_url'] ?? '');
            if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && app()->bound(LocalizationServiceInterface::class)) {
                $item['cta_url'] = app(LocalizationServiceInterface::class)->url($url, $market, $locale);
            }
        }
        unset($item);

        $cardsHtml = view('components.cms.card-grid', [
            'items' => $items,
            'grid_columns' => $columns,
            'mapping' => [
                'title' => 'name',
                'badge' => 'price',
                'subtitle' => 'period',
                'tags' => 'features',
                'url' => 'cta_url',
                'cta_label' => 'cta_text',
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
