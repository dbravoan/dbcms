<?php

declare(strict_types=1);

namespace App\Core\Blocks\Types;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;

class BeforeAfterBlock extends AbstractBlockType
{
    public function getType(): string
    {
        return 'before_after';
    }

    public function getName(): string
    {
        return 'Comparador Antes / Después';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-scale-balanced';
    }

    public function getCategory(): string
    {
        return 'media';
    }

    public function getDescription(): string
    {
        return 'Control deslizante interactivo para comparar dos imágenes (antes y después).';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Transformación de Rendimiento',
            'image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800',
            'after_image_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800',
            'before_label' => 'Antes (Monolito)',
            'after_label' => 'Después ('.config('cms.name').' Edge)',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $beforeImg = htmlspecialchars((string) ($payload['image_url'] ?? ''));
        $afterImg = htmlspecialchars((string) ($payload['after_image_url'] ?? ''));
        $beforeLbl = htmlspecialchars((string) ($payload['before_label'] ?? 'Antes'));
        $afterLbl = htmlspecialchars((string) ($payload['after_label'] ?? 'Después'));

        $headerHtml = ! empty($title) ? "<div class=\"text-center max-w-2xl mx-auto mb-6\"><h2 class=\"text-2xl font-bold cms-text flex items-center justify-center gap-2\"><span class=\"cms-accent\">&gt;_</span> {$title}</h2></div>" : '';

        return <<<HTML
        <div class="space-y-4 font-mono w-full max-w-4xl mx-auto">
            {$headerHtml}
            <div class="relative w-full h-80 sm:h-96 rounded-2xl overflow-hidden border cms-border select-none shadow-2xl"
                 x-data="{ position: 50, isDragging: false }"
                 @mousemove="if (isDragging) { const rect = \$el.getBoundingClientRect(); position = Math.max(0, Math.min(100, ((\$event.clientX - rect.left) / rect.width) * 100)); }"
                 @touchmove="if (isDragging) { const rect = \$el.getBoundingClientRect(); position = Math.max(0, Math.min(100, ((\$event.touches[0].clientX - rect.left) / rect.width) * 100)); }"
                 @mouseup="isDragging = false"
                 @touchend="isDragging = false"
                 @mouseleave="isDragging = false">
                <!-- After Image (Full background) -->
                <img src="{$afterImg}" alt="After" class="absolute inset-0 w-full h-full object-cover">
                <span class="absolute top-4 right-4 z-10 px-3 py-1 rounded-full text-[10px] font-bold bg-black/80 cms-accent border cms-border-accent backdrop-blur-md">{$afterLbl}</span>

                <!-- Before Image (Clipped overlay) -->
                <div class="absolute inset-0 overflow-hidden" :style="'width: ' + position + '%'">
                    <img src="{$beforeImg}" alt="Before" class="absolute inset-0 w-full h-full object-cover max-w-none" style="width: 100%; height: 100%;">
                    <span class="absolute top-4 left-4 z-10 px-3 py-1 rounded-full text-[10px] font-bold bg-black/80 cms-text border cms-border-strong backdrop-blur-md">{$beforeLbl}</span>
                </div>

                <!-- Draggable Divider Handle -->
                <div class="absolute top-0 bottom-0 w-1 cms-accent-bg cursor-ew-resize flex items-center justify-center "
                     :style="'left: ' + position + '%'"
                     @mousedown.prevent="isDragging = true"
                     @touchstart.prevent="isDragging = true">
                    <div class="w-8 h-8 rounded-full cms-surface-2 border-2 cms-border-accent flex items-center justify-center cms-accent text-xs font-black shadow-lg">
                        &harr;
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }
}
