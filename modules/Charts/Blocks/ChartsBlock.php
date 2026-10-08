<?php

declare(strict_types=1);

namespace Modules\Charts\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use App\Models\Market;
use Modules\Charts\Services\ChartRendererService;

class ChartsBlock extends AbstractBlockType implements TranslatableBlockInterface
{
    protected ChartRendererService $renderer;

    public function __construct(
        ?ChartRendererService $renderer = null
    ) {
        $this->renderer = $renderer ?? app(ChartRendererService::class);
    }

    public function getType(): string
    {
        return 'charts';
    }

    public function getName(): string
    {
        return 'Gráficas y Visualización de Datos';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-chart-pie';
    }

    public function getCategory(): string
    {
        return 'interactive';
    }

    public function getDescription(): string
    {
        return 'Gráficas animadas de barras, líneas continuas, anillos circulares y contadores numéricos.';
    }

    public function getRequiredModule(): ?string
    {
        return 'charts';
    }

    /** @return list<string> */
    public function getTranslationPaths(): array
    {
        return ['title', 'items.*.label', 'donut_label', 'donut_subtext', 'metric_label', 'metric_description'];
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Métricas de Desempeño y Rendimiento',
            'chart_type' => 'bar', // 'bar', 'donut', 'line', 'metric'
            'items' => [
                ['label' => 'Velocidad de Carga', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                ['label' => 'SEO & GEO Indexing', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                ['label' => 'Conversión de Leads', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
            ],
            'donut_percentage' => 88,
            'donut_label' => 'Tasa de Eficiencia Operativa',
            'donut_subtext' => 'Reducción de coste perimetral',
            'metric_value' => '99.99%',
            'metric_label' => 'Disponibilidad de Servicio',
            'metric_description' => 'Infraestructura global con tolerancia a fallos',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $type = (string) ($payload['chart_type'] ?? 'bar');
        $title = (string) ($payload['title'] ?? '');

        $renderedChart = match ($type) {
            'donut' => $this->renderer->renderDonut(
                (float) ($payload['donut_percentage'] ?? 85),
                (string) ($payload['donut_label'] ?? 'Eficiencia'),
                (string) ($payload['donut_subtext'] ?? ''),
                ['color' => $payload['color'] ?? 'var(--cms-accent)']
            ),
            'line' => $this->renderer->renderLine(
                (array) ($payload['items'] ?? [
                    ['point' => 'Mes 1', 'value' => 20],
                    ['point' => 'Mes 2', 'value' => 45],
                    ['point' => 'Mes 3', 'value' => 78],
                    ['point' => 'Mes 4', 'value' => 95],
                ]),
                ['title' => $title]
            ),
            'metric' => $this->renderer->renderMetric(
                (string) ($payload['metric_value'] ?? '100%'),
                (string) ($payload['metric_label'] ?? 'Métrica Clave'),
                (string) ($payload['metric_description'] ?? '')
            ),
            default => $this->renderer->renderBar(
                (array) ($payload['items'] ?? []),
                ['title' => $title]
            ),
        };

        if ($type === 'metric' || $type === 'donut') {
            return <<<HTML
            <div class="max-w-xl mx-auto w-full">
                {$renderedChart}
            </div>
            HTML;
        }

        return $renderedChart;
    }
}
