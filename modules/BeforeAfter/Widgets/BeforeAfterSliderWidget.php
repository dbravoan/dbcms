<?php

declare(strict_types=1);

namespace Modules\BeforeAfter\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use Illuminate\Contracts\View\View;

class BeforeAfterSliderWidget extends BaseWidgetComponent
{
    public static function getKey(): string
    {
        return 'before_after.comparison_slider';
    }

    public static function getTitle(): string
    {
        return 'Interactive Before & After Comparison Slider';
    }

    public static function getOptionsSchema(): array
    {
        return [
            'before_image' => ['type' => 'string', 'label' => 'Before Image URL', 'required' => true],
            'after_image' => ['type' => 'string', 'label' => 'After Image URL', 'required' => true],
            'before_label' => ['type' => 'string', 'default' => 'Before'],
            'after_label' => ['type' => 'string', 'default' => 'After'],
        ];
    }

    public function render(array $parameters = []): View|string
    {
        return $this->renderWidgetView('before_after', 'comparison_slider', [
            'beforeImage' => $parameters['before_image'] ?? 'https://placehold.co/800x500/0f172a/white?text=Before',
            'afterImage' => $parameters['after_image'] ?? 'https://placehold.co/800x500/4f46e5/white?text=After',
            'beforeLabel' => $parameters['before_label'] ?? 'Before',
            'afterLabel' => $parameters['after_label'] ?? 'After',
        ]);
    }
}
