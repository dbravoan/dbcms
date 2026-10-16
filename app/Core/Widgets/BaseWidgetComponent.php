<?php

declare(strict_types=1);

namespace App\Core\Widgets;

use App\Core\Blocks\AbstractBlockType;
use App\Services\Cms\FrontViewResolver;

abstract class BaseWidgetComponent extends AbstractBlockType
{
    public function getIcon(): string
    {
        return 'fa-solid fa-puzzle-piece';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDefaultPayload(): array
    {
        $payload = [];
        foreach ($this->getEditorSchema() as $key => $field) {
            $payload[$key] = $field['default'] ?? (in_array($field['type'] ?? 'text', ['array', 'json', 'list', 'repeater'], true) ? [] : '');
        }

        return $payload;
    }

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
    protected function renderWidgetView(string $module, string $viewName, array $data = []): string
    {
        return view($this->widgetView($module, $viewName), $data)->render();
    }
}
