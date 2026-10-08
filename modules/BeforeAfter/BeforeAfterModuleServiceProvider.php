<?php

declare(strict_types=1);

namespace Modules\BeforeAfter;

use App\Core\Modules\BaseModuleServiceProvider;
use Modules\BeforeAfter\Widgets\BeforeAfterSliderWidget;

class BeforeAfterModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'before_after';

    protected string $name = 'Before & After Comparison';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->bind('widget.before_after.comparison_slider', fn () => BeforeAfterSliderWidget::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'before_after');
    }

    public function getWidgets(): array
    {
        return [
            BeforeAfterSliderWidget::getKey() => BeforeAfterSliderWidget::class,
        ];
    }
}
