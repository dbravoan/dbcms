<?php

declare(strict_types=1);

namespace BlockPackages\Basic\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;

class TestimonialsBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'items.*.quote', 'items.*.role'];
    }

    public function getType(): string
    {
        return 'testimonials';
    }

    public function getName(): string
    {
        return 'Citas y Testimonios';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-star';
    }

    public function getCategory(): string
    {
        return 'marketing';
    }

    public function getDescription(): string
    {
        return 'Citas de clientes, testimonios de casos de éxito y logotipos de validación social.';
    }

    /**
     * The block manages its own grid: it is added as a section, never nested
     * inside a column (a grid inside a column renders as a grid inside a grid).
     */
    public function isFullWidth(): bool
    {
        return true;
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Lo que dicen los equipos de ingeniería',
            'items' => [
                [
                    'author' => 'Elena Martínez',
                    'role' => 'CTO en TechNexus',
                    'quote' => config('cms.name').' redujo nuestro tiempo de entrega en despliegues internacionales de semanas a minutos.',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200',
                ],
                [
                    'author' => 'Marc Soler',
                    'role' => 'Lead Architect en DataCore',
                    'quote' => 'El soporte para GEO y generación automática de llms.txt multiplicó nuestras citas en motores IA.',
                    'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200',
                ],
            ],
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $rawItems = (array) ($payload['items'] ?? []);
        $items = array_map(function ($item) {
            $item = (array) $item;
            $item['author'] = $item['author'] ?? 'Cliente';
            $item['quote'] = $item['quote'] ?? '';
            $rating = (int) ($item['rating'] ?? 5);
            if ($rating > 0) {
                $item['badge'] = str_repeat('★', min(5, max(1, $rating)));
            }

            return $item;
        }, $rawItems);

        $columns = count($items) === 2 ? '2' : '3';

        $headerHtml = ! empty($title) ? "<div class=\"text-center max-w-2xl mx-auto mb-10\"><h2 class=\"text-2xl sm:text-3xl font-black cms-text flex items-center justify-center gap-2\"><span class=\"cms-accent\">&gt;_</span> {$title}</h2></div>" : '';

        $cardsHtml = view('components.cms.card-grid', [
            'items' => $items,
            'grid_columns' => $columns,
            'mapping' => [
                'title' => 'author',
                'subtitle' => 'quote',
                'meta' => 'role',
                'badge' => 'badge',
                'image' => 'avatar',
            ],
            'style' => 'glass',
        ])->render();

        return <<<HTML
        <div class="space-y-6 w-full font-mono">
            {$headerHtml}
            {$cardsHtml}
        </div>
        HTML;
    }
}
