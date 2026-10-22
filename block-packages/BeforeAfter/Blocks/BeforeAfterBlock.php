<?php

declare(strict_types=1);

namespace BlockPackages\BeforeAfter\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;

class BeforeAfterBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'before_label', 'after_label'];
    }

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
        $view = app(FrontViewResolver::class)->resolve('before_after.blocks.comparison', 'before_after::blocks.comparison');

        return view($view, [
            'title' => (string) ($payload['title'] ?? ''),
            'beforeImage' => (string) ($payload['image_url'] ?? ''),
            'afterImage' => (string) ($payload['after_image_url'] ?? ''),
            'beforeLabel' => (string) ($payload['before_label'] ?? 'Antes'),
            'afterLabel' => (string) ($payload['after_label'] ?? 'Después'),
        ])->render();
    }
}
