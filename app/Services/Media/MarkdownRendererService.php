<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Core\Widgets\WidgetComponentInterface;
use App\Services\Content\SafeContentRenderer;

class MarkdownRendererService
{
    public function __construct(private readonly SafeContentRenderer $safe = new SafeContentRenderer) {}

    /**
     * Compiles Markdown prose into sanitized HTML and resolves embedded widget shortcodes.
     */
    public function render(string $markdownContent): string
    {
        $html = $this->safe->markdown($markdownContent);

        // 2. Resolve widget shortcodes: [widget:blog.featured_posts limit="3"]
        return $this->resolveShortcodes($html);
    }

    /**
     * Renders pure HTML content directly and resolves embedded widget shortcodes.
     */
    public function renderHtml(string $htmlContent): string
    {
        return $this->resolveShortcodes($this->safe->html($htmlContent));
    }

    /**
     * Resolves widget shortcodes inside HTML markup.
     */
    public function resolveShortcodes(string $html): string
    {
        return (string) preg_replace_callback(
            '/\[widget:([a-zA-Z0-9_\-\.]+)(.*?)\]/',
            function (array $matches): string {
                $widgetKey = $matches[1];
                $attributesRaw = $matches[2];
                $parameters = $this->parseShortcodeAttributes($attributesRaw);

                return $this->resolveWidget($widgetKey, $parameters);
            },
            $html
        );
    }

    /**
     * Parses shortcode key="val" attributes.
     *
     * @return array<string, mixed>
     */
    protected function parseShortcodeAttributes(string $raw): array
    {
        $attributes = [];
        preg_match_all('/(\w+)=["\']([^"\']+)["\']/', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }

        return $attributes;
    }

    /**
     * Resolves a widget by key from active modules.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function resolveWidget(string $widgetKey, array $parameters = []): string
    {
        // Check container or module widget registry
        if (app()->bound("widget.{$widgetKey}")) {
            $widgetClass = app("widget.{$widgetKey}");
            if (is_string($widgetClass) && class_exists($widgetClass)) {
                /** @var WidgetComponentInterface $instance */
                $instance = app($widgetClass);
                $rendered = $instance->render($parameters);

                return is_string($rendered) ? $rendered : $rendered->render();
            }
        }

        return "<!-- widget: {$widgetKey} -->";
    }
}
