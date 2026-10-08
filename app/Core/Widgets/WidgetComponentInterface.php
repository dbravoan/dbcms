<?php

declare(strict_types=1);

namespace App\Core\Widgets;

use Illuminate\Contracts\View\View;

interface WidgetComponentInterface
{
    /**
     * Unique widget key (e.g. 'blog.featured_posts', 'gallery.grid', 'forms.embed').
     */
    public static function getKey(): string;

    /**
     * Human-readable title of the widget.
     */
    public static function getTitle(): string;

    /**
     * Schema describing the configurable attributes/options for this widget.
     *
     * @return array<string, mixed>
     */
    public static function getOptionsSchema(): array;

    /**
     * Renders the widget with the given parameters, active market, and locale.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function render(array $parameters = []): View|string;
}
