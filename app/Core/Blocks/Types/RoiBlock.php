<?php

declare(strict_types=1);

namespace App\Core\Blocks\Types;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;
use Modules\Charts\Services\ChartRendererService;

class RoiBlock extends AbstractBlockType
{
    public function __construct(
        protected ?ChartRendererService $chartRenderer = null
    ) {
        if ($this->chartRenderer === null && class_exists(ChartRendererService::class)) {
            $this->chartRenderer = app(ChartRendererService::class);
        }
    }

    public function getType(): string
    {
        return 'roi';
    }

    public function getName(): string
    {
        return 'Calculadora y Retorno de Inversión (ROI)';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-chart-line';
    }

    public function getCategory(): string
    {
        return 'interactive';
    }

    public function getDescription(): string
    {
        return 'Sección de cálculo de retorno comercial y ahorro proyectado que compone e integra primitivas de gráficas.';
    }

    public function getRequiredModule(): ?string
    {
        return 'charts';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Calculadora de Retorno de Inversión (ROI)',
            'description' => 'Comprueba el impacto directo en reducción de costes de infraestructura y aumento de conversión con la arquitectura perimetral de '.config('cms.name').'.',
            'initial_investment' => 12000,
            'annual_savings' => 38000,
            'project_years' => 3,
            'current_solution_cost' => 50000,
            'optimized_solution_cost' => 12000,
            'payback_months' => 4,
            'currency_symbol' => '€',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? 'Calculadora de Retorno de Inversión (ROI)'));
        $desc = htmlspecialchars((string) ($payload['description'] ?? ''));
        $currency = htmlspecialchars((string) ($payload['currency_symbol'] ?? '€'));

        $investment = (float) ($payload['initial_investment'] ?? 12000);
        $annualSavings = (float) ($payload['annual_savings'] ?? 38000);
        $years = (int) ($payload['project_years'] ?? 3);
        $currentCost = (float) ($payload['current_solution_cost'] ?? 50000);
        $optimizedCost = (float) ($payload['optimized_solution_cost'] ?? 12000);
        $paybackMonths = (int) ($payload['payback_months'] ?? 4);

        if ($investment <= 0) {
            $investment = 1;
        }

        $totalSavings = ($annualSavings * $years) - $investment;
        $roiPercentage = round(($totalSavings / $investment) * 100);
        $efficiencyPct = $currentCost > 0 ? round((($currentCost - $optimizedCost) / $currentCost) * 100) : 75;

        // Visual chart components (reused cleanly from ChartRendererService)
        $donutHtml = '';
        $barHtml = '';
        $metricPayback = '';
        $metricRoi = '';

        if ($this->chartRenderer) {
            $donutHtml = $this->chartRenderer->renderDonut(
                $efficiencyPct,
                'Eficiencia Operativa',
                'Reducción de coste perimetral',
                ['color' => 'var(--cms-accent)', 'size' => 170]
            );

            $barHtml = $this->chartRenderer->renderBar([
                ['label' => 'Coste Actual de Infraestructura', 'value' => $currentCost, 'color' => '#EF4444', 'unit' => " {$currency}/año"],
                ['label' => 'Coste Optimizado con '.config('cms.name'), 'value' => $optimizedCost, 'color' => 'var(--cms-accent)', 'unit' => " {$currency}/año"],
            ], ['title' => 'Comparativa Anual de Costes']);

            $metricPayback = $this->chartRenderer->renderMetric(
                "{$paybackMonths} Meses",
                'Tiempo de Amortización (Payback)',
                'Recuperación de la inversión inicial estimada'
            );

            $metricRoi = $this->chartRenderer->renderMetric(
                "+{$roiPercentage}%",
                'Retorno Neto Proyectado (ROI)',
                "Retorno total estimado en {$years} años"
            );
        }

        return <<<HTML
        <div class="space-y-8 font-mono">
            <div class="text-center max-w-2xl mx-auto space-y-2">
                <h2 class="text-2xl sm:text-3xl font-black cms-text flex items-center justify-center gap-2">
                    <span class="cms-accent">&gt;_</span> {$title}
                </h2>
                <p class="text-xs cms-text-muted">{$desc}</p>
            </div>

            <!-- Key Financial KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {$metricRoi}
                {$metricPayback}
            </div>

            <!-- Visual Charts Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
                <div class="lg:col-span-2 w-full">
                    {$barHtml}
                </div>
                <div class="w-full">
                    {$donutHtml}
                </div>
            </div>
        </div>
        HTML;
    }
}
