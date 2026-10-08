<?php

declare(strict_types=1);

namespace Modules\Charts;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Modules\BaseModuleServiceProvider;
use Modules\Charts\Blocks\ChartsBlock;
use Modules\Charts\Widgets\KpiCountersWidget;

class ChartsModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'charts';

    protected string $name = 'Charts & Data Visualization';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->bind('widget.charts.kpi_counters', fn () => KpiCountersWidget::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'charts');

        if ($this->app->bound(BlockRegistryInterface::class)) {
            $this->app->make(BlockRegistryInterface::class)->register(new ChartsBlock);
        }
    }

    public function getWidgets(): array
    {
        return [
            KpiCountersWidget::getKey() => KpiCountersWidget::class,
        ];
    }
}
