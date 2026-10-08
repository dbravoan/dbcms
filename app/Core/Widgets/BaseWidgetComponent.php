<?php

declare(strict_types=1);

namespace App\Core\Widgets;

use App\Services\Cms\FrontViewResolver;
use Illuminate\Contracts\View\View;

abstract class BaseWidgetComponent implements WidgetComponentInterface
{
    /**
     * Resolve a widget view honouring the active theme override paths.
     *
     * @return view-string
     */
    protected function widgetView(string $module, string $viewName): string
    {
        /** @var view-string $fallback */
        $fallback = "{$module}::widgets.{$viewName}";

        return app(FrontViewResolver::class)->widget($module, $viewName, $fallback);
    }

    /**
     * Helper to render widget view with active theme fallback.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderWidgetView(string $module, string $viewName, array $data = []): View
    {
        return view($this->widgetView($module, $viewName), $data);
    }
}
