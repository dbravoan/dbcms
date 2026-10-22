<?php

declare(strict_types=1);

namespace BlockPackages\Basic\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;

class CtaBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'content', 'cta_text'];
    }

    public function getType(): string
    {
        return 'cta';
    }

    public function getName(): string
    {
        return 'Llamada a la Acción Destacada (CTA)';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-bullseye';
    }

    public function getCategory(): string
    {
        return 'marketing';
    }

    public function getDescription(): string
    {
        return 'Banner de alta conversión con título, descripción y botón de acción directa.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => '¿Listo para escalar con '.config('cms.name').'?',
            'content' => 'Despliega arquitectura moderna, SEO de alta conversión y soporte GEO nativo.',
            'cta_text' => 'Comenzar Ahora',
            'cta_url' => '/contacto',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? ('¿Listo para escalar con '.config('cms.name').'?')));
        $desc = htmlspecialchars((string) ($payload['content'] ?? ''));
        $ctaText = htmlspecialchars((string) ($payload['cta_text'] ?? 'Comenzar Ahora'));
        $rawUrl = (string) ($payload['cta_url'] ?? '/contacto');

        $ctaUrl = $rawUrl;
        if (str_starts_with($rawUrl, '/') && ! str_starts_with($rawUrl, '//') && app()->bound(LocalizationServiceInterface::class)) {
            $ctaUrl = app(LocalizationServiceInterface::class)->url($rawUrl, $market, $locale);
        }
        $ctaUrl = htmlspecialchars($ctaUrl);
        $action = $rawUrl === '' ? '' : "<a href=\"{$ctaUrl}\" class=\"cms-btn cms-btn-primary text-xs sm:text-sm py-3 px-8\">{$ctaText} &rarr;</a>";

        return <<<HTML
        <div class="text-center p-8 sm:p-12 rounded-3xl bg-gradient-to-r from-[#121212] via-[#1A1A1A] to-[#121212] border cms-border-accent  font-mono">
            <h2 class="text-2xl sm:text-4xl font-black cms-text mb-4">{$title}</h2>
            <p class="text-xs sm:text-sm cms-text-muted max-w-2xl mx-auto mb-6 leading-relaxed">{$desc}</p>
            {$action}
        </div>
        HTML;
    }
}
