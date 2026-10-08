<?php

declare(strict_types=1);

namespace Modules\Charts\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use Illuminate\Contracts\View\View;

class KpiCountersWidget extends BaseWidgetComponent
{
    public static function getKey(): string
    {
        return 'charts.kpi_counters';
    }

    public static function getTitle(): string
    {
        return 'Animated KPI Counter Stats';
    }

    public static function getOptionsSchema(): array
    {
        return [
            'stats' => ['type' => 'array', 'label' => 'List of KPI metrics'],
        ];
    }

    public function render(array $parameters = []): View|string
    {

        return $this->renderWidgetView('charts', 'kpi_counters', [
            'stats' => $parameters['stats'] ?? [],
        ]);
    }
}
