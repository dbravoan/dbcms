<?php

declare(strict_types=1);

namespace BlockPackages\Basic\Blocks;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;

class HeroBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'content', 'cta_text', 'secondary_cta_text', 'badge'];
    }

    public function getType(): string
    {
        return 'hero';
    }

    public function getName(): string
    {
        return 'Encabezado Hero Principal';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-rocket';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getDescription(): string
    {
        return 'Sección de portada de alto impacto con título, subtítulo enriquecido, botón CTA e imagen destacada.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Infraestructura web de alto rendimiento',
            'content' => 'Plataforma empresarial con distribución global, baja latencia y arquitectura modular.',
            'cta_text' => 'Comenzar ahora',
            'cta_url' => '/contacto',
            'secondary_cta_text' => 'Ver documentación',
            'secondary_cta_url' => '/blog',
            'image_url' => '',
            'video_url' => '',
            'badge' => 'v1.0 LTS Enterprise',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $link = static function (string $url) use ($market, $locale): string {
            if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && app()->bound(LocalizationServiceInterface::class)) {
                $url = app(LocalizationServiceInterface::class)->url($url, $market, $locale);
            }

            return htmlspecialchars($url);
        };
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $content = (string) ($payload['content'] ?? '');
        $ctaText = htmlspecialchars((string) ($payload['cta_text'] ?? ''));
        $ctaUrl = $link((string) ($payload['cta_url'] ?? ''));
        $secCtaText = htmlspecialchars((string) ($payload['secondary_cta_text'] ?? ''));
        $secCtaUrl = $link((string) ($payload['secondary_cta_url'] ?? ''));
        $imageUrl = htmlspecialchars((string) ($payload['image_url'] ?? ''));
        $videoUrl = htmlspecialchars((string) ($payload['video_url'] ?? ''));
        $badge = htmlspecialchars((string) ($payload['badge'] ?? ''));

        $badgeHtml = ! empty($badge) ? "<span class=\"inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold cms-chip font-mono mb-4\"><span class=\"w-1.5 h-1.5 rounded-full cms-accent-bg animate-pulse\"></span>{$badge}</span>" : '';

        $ctaHtml = ! empty($ctaText) && $ctaUrl !== '' ? "<a href=\"{$ctaUrl}\" class=\"cms-btn cms-btn-primary text-xs sm:text-sm py-3 px-6\">{$ctaText} &rarr;</a>" : '';
        $secCtaHtml = ! empty($secCtaText) && $secCtaUrl !== '' ? "<a href=\"{$secCtaUrl}\" class=\"cms-btn cms-btn-ghost px-6 py-3 rounded-lg text-xs sm:text-sm font-bold transition-colors\">{$secCtaText}</a>" : '';

        $actionsHtml = (! empty($ctaHtml) || ! empty($secCtaHtml)) ? "<div class=\"flex flex-wrap items-center gap-4 pt-2\">{$ctaHtml}{$secCtaHtml}</div>" : '';

        $mediaColHtml = '';
        if (! empty($videoUrl)) {
            $posterAttr = ! empty($imageUrl) ? "poster=\"{$imageUrl}\"" : '';
            $mediaColHtml = <<<HTML
            <div class="relative w-full rounded-2xl overflow-hidden border cms-border shadow-2xl bg-black">
                <video src="{$videoUrl}" {$posterAttr} autoplay loop muted playsinline class="w-full h-auto object-cover max-h-[440px]">
                </video>
            </div>
            HTML;
        } elseif (! empty($imageUrl)) {
            $mediaColHtml = <<<HTML
            <div class="relative w-full rounded-2xl overflow-hidden border cms-border shadow-2xl">
                <img src="{$imageUrl}" alt="{$title}" class="w-full h-auto object-cover max-h-[440px]">
            </div>
            HTML;
        }

        $gridCols = (! empty($imageUrl) || ! empty($videoUrl)) ? 'grid-cols-1 lg:grid-cols-2 items-center gap-12' : 'grid-cols-1 max-w-3xl';

        return <<<HTML
        <div class="grid {$gridCols} py-8">
            <div class="space-y-6">
                {$badgeHtml}
                <h1 class="text-3xl sm:text-5xl font-black cms-text tracking-tight leading-tight">
                    {$title}
                </h1>
                <div class="text-sm sm:text-base cms-text-muted leading-relaxed font-mono">
                    {$content}
                </div>
                {$actionsHtml}
            </div>
            {$mediaColHtml}
        </div>
        HTML;
    }
}
