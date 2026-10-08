<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Sections\SectionLayout;
use App\Core\Widgets\WidgetComponentInterface;
use App\Models\FilterPageSection;
use App\Models\Market;
use App\Services\Media\MarkdownRendererService;
use Illuminate\Support\Facades\Log;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostSection;
use Modules\Gallery\Models\Gallery;
use Modules\Pages\Models\PageSection;
use Modules\Shop\Models\ProductSection;
use Modules\Shop\Widgets\FeaturedProductsWidget;

class SectionRendererService
{
    public function __construct(
        protected MarkdownRendererService $markdownRenderer,
        protected LocalizationServiceInterface $localization,
        protected BlockRegistryInterface $blockRegistry
    ) {}

    /**
     * Renders a PageSection, PostSection, or ProductSection with its styling manifest, fullwidth wrapper, and scroll-magic animations.
     */
    /** @param array<string, mixed> $section */
    public function renderSection(PageSection|PostSection|ProductSection|FilterPageSection|array $section, ?Market $market = null, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        // Stable id so the front can deep-link to this exact block in the
        // backoffice editor (`?section=<id>`), robust against reordering.
        $sectionId = is_array($section)
            ? (int) ($section['id'] ?? 0)
            : (int) $section->getKey();
        $sectionAttr = $sectionId > 0 ? ' data-cms-section="'.$sectionId.'"' : '';

        if (is_array($section)) {
            $content = $section;
            $styling = (array) ($section['styling_json'] ?? $section);
            $type = (string) ($section['type'] ?? 'text');
            $widgetKey = (string) ($section['widget_key'] ?? '');
        } else {
            $translation = null;
            if (isset($section->translations) && method_exists($section->translations, 'where')) {
                $translation = $section->translations->where('locale', $locale)->first()
                    ?? $section->translations->first();
            }

            // Merge payload, content_json, and section settings
            $settings = (array) ($section->settings ?? []);
            $payload = [];
            $contentJson = [];
            if ($translation !== null) {
                $transAttrs = $translation->getAttributes();
                $payload = (array) (array_key_exists('payload', $transAttrs) ? $translation->getAttribute('payload') : []);
                $contentJson = (array) (array_key_exists('content_json', $transAttrs) ? $translation->getAttribute('content_json') : []);
            }

            $content = array_merge($settings, $contentJson, $payload);
            if ($translation) {
                if (! empty($translation->title)) {
                    $content['title'] = $translation->title;
                }
                if (! empty($translation->content)) {
                    $content['content'] = $translation->content;
                }
            }

            $styling = (array) ($section->styling_json ?? []);
            $type = (string) ($section->type ?? ($content['type'] ?? 'text'));
            $widgetKey = (string) ($section->widget_key ?? ($content['widget_key'] ?? ''));
        }

        // 1. Canonical contract: the localized content wins over the master
        //    payload for every field (section and blocks), and the columns are
        //    read through App\Core\Sections\SectionLayout, so the editor, the
        //    API and the front office agree.
        $resolved = SectionLayout::resolve($styling, $content);

        $columnsLayout = (string) ($resolved['columns_layout'] ?? '1');
        $columns = SectionLayout::columns($resolved);

        if ($columnsLayout !== '1' && $columns !== []) {
            $innerHtml = $this->renderColumns($columnsLayout, $columns, $market, $locale);
        } else {
            $innerHtml = $this->renderBlockContent($type, $resolved, $market, $locale, $widgetKey);
        }

        // 2. Animation & styling attributes (same resolved payload)
        $scrollMagic = (array) ($resolved['scroll_magic'] ?? []);
        $behavior = (string) ($scrollMagic['behavior'] ?? ($resolved['animation'] ?? 'fadeInUp'));
        $delay = (string) ($scrollMagic['delay'] ?? ($resolved['animation_delay'] ?? '0.2s'));
        $duration = (string) ($resolved['animation_duration'] ?? '1s');
        $containerStyle = (string) ($resolved['container_style'] ?? 'cms-card');
        $bgType = (string) ($resolved['bg_type'] ?? 'none');

        $scrollMagicAttr = $behavior !== 'none' ? "data-scroll-magic=\"{$behavior}\"" : 'data-scroll-magic';
        $scrollAttrsList = [
            $scrollMagicAttr,
            "data-scroll-effect=\"{$behavior}\"",
            "data-scroll-delay=\"{$delay}\"",
            "data-scroll-duration=\"{$duration}\"",
        ];

        if (isset($scrollMagic['scrub']) && is_string($scrollMagic['scrub'])) {
            $scrollAttrsList[] = "data-scroll-scrub=\"{$scrollMagic['scrub']}\"";
        }
        if ($behavior === 'video-playback' || $bgType === 'video') {
            $scrollAttrsList[] = 'data-scroll-video="playback"';
        }

        // Un fondo con comportamiento parallax necesita el motor de scroll,
        // aunque la animación de entrada no lo pidiera.
        if ($bgType === 'image' && ($resolved['bg_behavior'] ?? 'normal') === 'parallax'
            && ! in_array('data-scroll-scrub="parallax"', $scrollAttrsList, true)) {
            $scrollAttrsList[] = 'data-scroll-scrub="parallax"';
        }

        $scrollAttrs = implode(' ', $scrollAttrsList);

        $styleClass = match ($containerStyle) {
            'cyber-glass' => 'cms-card backdrop-blur-xl border cms-border-accent',
            'neon-glow' => 'cms-card  border cms-border-accent',
            'transparent' => 'bg-transparent border-0',
            'full-width-dark' => 'cms-surface-2 border-y cms-border',
            default => 'cms-card',
        };

        // Inline CSS styling (Background color & Borders)
        $inlineStyles = [];
        $bgColor = (string) ($resolved['bg_color'] ?? '');
        if (! empty($bgColor)) {
            $inlineStyles[] = "background-color: {$bgColor};";
        }

        $borderTop = $resolved['border_top'] ?? null;
        if (is_array($borderTop) && ! empty($borderTop['enabled'])) {
            $bWidth = htmlspecialchars((string) ($borderTop['width'] ?? '1px'));
            $bStyle = htmlspecialchars((string) ($borderTop['style'] ?? 'solid'));
            $bColor = htmlspecialchars((string) ($borderTop['color'] ?? 'var(--cms-accent)'));
            $inlineStyles[] = "border-top: {$bWidth} {$bStyle} {$bColor};";
        }

        $borderBottom = $resolved['border_bottom'] ?? null;
        if (is_array($borderBottom) && ! empty($borderBottom['enabled'])) {
            $bWidth = htmlspecialchars((string) ($borderBottom['width'] ?? '1px'));
            $bStyle = htmlspecialchars((string) ($borderBottom['style'] ?? 'solid'));
            $bColor = htmlspecialchars((string) ($borderBottom['color'] ?? 'var(--cms-accent)'));
            $inlineStyles[] = "border-bottom: {$bWidth} {$bStyle} {$bColor};";
        }

        $styleAttr = ! empty($inlineStyles) ? 'style="'.implode(' ', $inlineStyles).'"' : '';

        // Container Width
        $contentWidth = (string) ($resolved['content_width'] ?? 'centered');
        $innerContainerClass = match ($contentWidth) {
            'full' => 'w-full relative z-10',
            'narrow' => 'max-w-4xl mx-auto relative z-10',
            default => 'max-w-7xl mx-auto relative z-10',
        };

        // Background Media (Image / Video)
        $bgMediaHtml = '';
        if ($bgType === 'image') {
            $imageUrl = htmlspecialchars((string) ($resolved['bg_image_url'] ?? ''));
            $overlay = htmlspecialchars((string) ($resolved['bg_image_overlay'] ?? 'rgba(0,0,0,0.5)'));
            if (! empty($imageUrl)) {
                $bgMediaHtml = $this->renderImageBackground($imageUrl, $overlay, (string) ($resolved['bg_behavior'] ?? 'normal'));
            }
        } elseif ($bgType === 'video') {
            $videoUrl = htmlspecialchars((string) ($resolved['bg_video_url'] ?? ''));
            $poster = htmlspecialchars((string) ($resolved['bg_video_poster'] ?? ''));
            $overlay = htmlspecialchars((string) ($resolved['bg_video_overlay'] ?? 'rgba(0,0,0,0.5)'));
            $posterAttr = ! empty($poster) ? "poster=\"{$poster}\"" : '';
            if (! empty($videoUrl)) {
                $bgMediaHtml = <<<HTML
                <div class="absolute inset-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
                    <video class="cms-bg-video w-full h-full object-cover" autoplay loop muted playsinline {$posterAttr}>
                        <source src="{$videoUrl}" type="video/mp4">
                    </video>
                    <div class="absolute inset-0" style="background-color: {$overlay};"></div>
                </div>
                HTML;
            }
        }

        // Top & Bottom SVG Shape Dividers
        $topDividerConfig = $resolved['divider_top'] ?? null;
        $topDivider = is_array($topDividerConfig) ? $this->renderShapeDivider('top', $topDividerConfig) : '';

        $bottomDividerConfig = $resolved['divider_bottom'] ?? null;
        $bottomDivider = is_array($bottomDividerConfig) ? $this->renderShapeDivider('bottom', $bottomDividerConfig) : '';

        return <<<HTML
        <section class="w-full my-8 relative overflow-hidden {$styleClass} p-6 sm:p-10 transition-all" {$styleAttr} {$scrollAttrs}{$sectionAttr}>
            {$bgMediaHtml}
            {$topDivider}
            <div class="{$innerContainerClass}">
                {$innerHtml}
            </div>
            {$bottomDivider}
        </section>
        HTML;
    }

    /**
     * Renders an image background honoring its behaviour.
     *
     * `normal` paints it as a CSS background; `fixed` pins it to the viewport
     * (`background-attachment: fixed`); `parallax` emits a real `<img>` layer
     * that the shared scroll engine translates (`data-scroll-scrub="parallax"`).
     */
    protected function renderImageBackground(string $imageUrl, string $overlay, string $behavior): string
    {
        if ($behavior === 'parallax') {
            // The section wrapper already carries `data-scroll-scrub="parallax"`
            // (see the scroll_magic payload); the engine moves `.cms-bg-layer`.
            return <<<HTML
            <div class="absolute inset-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
                <img src="{$imageUrl}" alt="" aria-hidden="true" class="cms-bg-layer w-full h-full object-cover">
                <div class="absolute inset-0" style="background-color: {$overlay};"></div>
            </div>
            HTML;
        }

        $attachment = $behavior === 'fixed' ? 'background-attachment: fixed;' : '';

        return <<<HTML
            <div class="absolute inset-0 w-full h-full bg-cover bg-center -z-10" style="background-image: url('{$imageUrl}'); {$attachment}"></div>
            <div class="absolute inset-0 -z-10 pointer-events-none" style="background-color: {$overlay};"></div>
        HTML;
    }

    /**
     * Renders a multi-column layout with individual column blocks.
     *
     * @param  array<int, mixed>  $columns
     */
    protected function renderColumns(string $layout, array $columns, ?Market $market = null, ?string $locale = null): string
    {
        $gridClass = match ($layout) {
            '2' => 'grid grid-cols-1 md:grid-cols-2 gap-8 items-start',
            '1-2' => 'grid grid-cols-1 md:grid-cols-3 gap-8 items-start',
            '2-1' => 'grid grid-cols-1 md:grid-cols-3 gap-8 items-start',
            '3' => 'grid grid-cols-1 md:grid-cols-3 gap-8 items-start',
            '4' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-start',
            default => 'grid grid-cols-1 md:grid-cols-2 gap-8 items-start',
        };

        $colsHtml = [];
        foreach ($columns as $idx => $col) {
            $colData = (array) $col;
            $colSpan = match ($layout) {
                '1-2' => ($idx === 1 ? 'md:col-span-2' : 'col-span-1'),
                '2-1' => ($idx === 0 ? 'md:col-span-2' : 'col-span-1'),
                default => 'col-span-1',
            };

            $colBlocks = (array) ($colData['blocks'] ?? []);
            $colBlocksHtml = [];
            foreach ($colBlocks as $subBlock) {
                if (is_array($subBlock)) {
                    $bType = (string) ($subBlock['type'] ?? 'text');
                    $bWidgetKey = (string) ($subBlock['widget_key'] ?? '');
                    $colBlocksHtml[] = $this->renderBlockContent($bType, $subBlock, $market, $locale, $bWidgetKey);
                }
            }

            $blocksRendered = implode("\n", $colBlocksHtml);

            // Column background: same contract as the section, applied to the
            // column wrapper (this is what makes "background" meaningful on a
            // column instead of only on the whole row).
            [$colStyleAttr, $colBgHtml, $colScrollAttr] = $this->columnBackgroundAttributes($colData);

            $colsHtml[] = <<<HTML
            <div class="cms-column {$colSpan} space-y-6 w-full relative overflow-hidden" {$colStyleAttr} {$colScrollAttr}>
                {$colBgHtml}
                <div class="relative z-10 space-y-6">
                    {$blocksRendered}
                </div>
            </div>
            HTML;
        }

        $allColsHtml = implode("\n", $colsHtml);

        return <<<HTML
        <div class="cms-section-grid {$gridClass} w-full">
            {$allColsHtml}
        </div>
        HTML;
    }

    /**
     * Resolves the background attributes of a single column.
     *
     * Columns support the same background contract as sections (color, image,
     * overlay and behaviour), which is why the appearance modal offers the
     * `Column` scope.
     *
     * @param  array<string, mixed>  $colData
     * @return array{0: string, 1: string, 2: string} [style attribute, background html, scroll attribute]
     */
    protected function columnBackgroundAttributes(array $colData): array
    {
        $style = (array) ($colData['style'] ?? []);
        $bgType = (string) ($style['bg_type'] ?? 'none');
        $inline = [];
        $bgHtml = '';
        $scrollAttr = '';

        $bgColor = (string) ($style['bg_color'] ?? '');

        if ($bgColor !== '') {
            $inline[] = 'background-color: '.htmlspecialchars($bgColor).';';
        }

        if ($bgType === 'image') {
            $imageUrl = htmlspecialchars((string) ($style['bg_image_url'] ?? ''));
            $overlay = htmlspecialchars((string) ($style['bg_image_overlay'] ?? 'rgba(0,0,0,0.5)'));
            $behavior = (string) ($style['bg_behavior'] ?? 'normal');

            if ($imageUrl !== '') {
                $bgHtml = $this->renderImageBackground($imageUrl, $overlay, $behavior);

                if ($behavior === 'parallax') {
                    $scrollAttr = 'data-scroll-scrub="parallax"';
                }
            }
        }

        // Padding/border of the column row.
        $borderTop = $style['border_top'] ?? null;
        if (is_array($borderTop) && ! empty($borderTop['enabled'])) {
            $inline[] = 'border-top: '.htmlspecialchars((string) ($borderTop['width'] ?? '1px')).' '.htmlspecialchars((string) ($borderTop['style'] ?? 'solid')).' '.htmlspecialchars((string) ($borderTop['color'] ?? 'var(--cms-accent)')).';';
        }

        $borderBottom = $style['border_bottom'] ?? null;
        if (is_array($borderBottom) && ! empty($borderBottom['enabled'])) {
            $inline[] = 'border-bottom: '.htmlspecialchars((string) ($borderBottom['width'] ?? '1px')).' '.htmlspecialchars((string) ($borderBottom['style'] ?? 'solid')).' '.htmlspecialchars((string) ($borderBottom['color'] ?? 'var(--cms-accent)')).';';
        }

        $padding = (string) ($style['padding'] ?? '');
        if ($padding !== '') {
            $inline[] = 'padding: '.htmlspecialchars($padding).';';
        }

        $styleAttr = $inline !== [] ? 'style="'.implode(' ', $inline).'"' : '';

        return [$styleAttr, $bgHtml, $scrollAttr];
    }

    /**
     * Renders inner HTML of a single block based on its content type.
     *
     * @param  array<string, mixed>  $content
     */
    public function renderBlockContent(string $type, array $content, ?Market $market = null, ?string $locale = null, string $widgetKey = ''): string
    {
        try {
            if ($type === 'widget' && ! empty($widgetKey)) {
                return $this->renderWidget($widgetKey, $content);
            }

            $block = $this->blockRegistry->get($type);
            if ($block !== null) {
                $requiredModule = $block->getRequiredModule();
                if ($requiredModule !== null && ! $this->isModuleActive($requiredModule)) {
                    return "<!-- {$type} silenced: {$requiredModule} module inactive -->";
                }

                return $block->render($content, $market, $locale);
            }

            return match ($type) {
                'hero' => $this->renderHero($content, $market, $locale),
                'text', 'markdown', 'html' => $this->renderText($content),
                'features' => $this->renderFeatures($content),
                'before_after' => $this->renderBeforeAfter($content),
                'gallery' => $this->renderGallery($content, $market, $locale),
                'faq' => $this->renderFaq($content),
                'cta' => $this->renderCta($content, $market, $locale),
                'code_embed' => $this->renderCodeEmbed($content),
                'widget_posts' => $this->renderWidgetPosts($content, $market, $locale),
                'widget_products', 'products' => $this->renderWidgetProducts($content, $market, $locale),
                'widget_form', 'form' => $this->renderWidgetForm($content, $market, $locale),
                'widget_stats' => $this->renderWidgetStats($content),
                'widget' => $this->renderWidget($widgetKey ?: (string) ($content['widget_key'] ?? ''), $content),
                default => $this->renderText($content),
            };
        } catch (\Throwable $e) {
            Log::warning("Block '{$type}' failed to render safely: {$e->getMessage()}");

            return "<!-- Block '{$type}' silenced safely -->";
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function renderShapeDivider(string $position, array $config): string
    {
        $type = (string) ($config['type'] ?? 'none');
        if ($type === 'none') {
            return '';
        }

        $color = htmlspecialchars((string) ($config['color'] ?? 'var(--cms-border)'));
        $height = (int) ($config['height'] ?? 60);
        $flip = ! empty($config['flip']);
        $transform = $flip ? 'transform: scaleX(-1);' : '';
        $posClass = $position === 'top' ? 'top-0' : 'bottom-0';

        $svgContent = match ($type) {
            'waves' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,0 C150,90 350,-40 500,60 C650,160 900,10 1200,40 L1200,{$height} L0,{$height} Z" fill="{$color}"></path>
            </svg>
            SVG,
            'tilt' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M1200 0L0 120V0h1200z" fill="{$color}"></path>
            </svg>
            SVG,
            'curve' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M600,112 C268.63,112 0,0 0,0 L1200,0 C1200,0 931.37,112 600,112 Z" fill="{$color}"></path>
            </svg>
            SVG,
            'triangle' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0 0l600 120 600-120v120H0z" fill="{$color}"></path>
            </svg>
            SVG,
            'spikes' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0 0l100 120 100-120 100 120 100-120 100 120 100-120 100 120 100-120 100 120 100-120 100 120 100-120v120H0z" fill="{$color}"></path>
            </svg>
            SVG,
            default => '',
        };

        if (empty($svgContent)) {
            return '';
        }

        return <<<HTML
        <div class="absolute left-0 right-0 w-full overflow-hidden leading-none pointer-events-none z-10 {$posClass}" style="height: {$height}px; {$transform}">
            {$svgContent}
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderHero(array $content, ?Market $market = null, ?string $locale = null): string
    {
        $defaultHeroTitle = $locale === 'ca' ? 'Plataforma CMS Multi-Mercat' : ($locale === 'en' ? 'Multi-Market CMS Platform' : 'Plataforma CMS Multi-Mercado');
        $title = htmlspecialchars((string) ($content['title'] ?? ($content['headline'] ?? $defaultHeroTitle)));
        $subtitle = htmlspecialchars((string) ($content['subtitle'] ?? ($content['subheadline'] ?? ($content['content'] ?? ($content['body'] ?? '')))));
        $ctaText = htmlspecialchars((string) ($content['cta_text'] ?? ($content['cta_label'] ?? '')));
        $rawCtaUrl = (string) ($content['cta_url'] ?? ($content['cta_link'] ?? '#'));

        $ctaUrl = $rawCtaUrl;
        if (str_starts_with($rawCtaUrl, '/')) {
            $ctaUrl = $this->localization->url($rawCtaUrl, $market, $locale);
        }
        $ctaUrl = htmlspecialchars($ctaUrl);
        $bgImage = ! empty($content['image_url']) ? (string) $content['image_url'] : null;
        $siteName = htmlspecialchars((string) config('cms.name', 'CMS'));

        $bgStyle = $bgImage ? "background-image: linear-gradient(rgba(10,10,10,0.85), rgba(10,10,10,0.85)), url('{$bgImage}'); background-size: cover; background-position: center;" : '';

        $ctaButton = ! empty($ctaText)
            ? "<a href=\"{$ctaUrl}\" class=\"cms-btn cms-btn-primary inline-flex items-center gap-2 mt-6 text-sm font-bold\">{$ctaText} &rarr;</a>"
            : '';

        return <<<HTML
        <div class="text-center py-10 md:py-16 rounded-2xl relative" style="{$bgStyle}">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border cms-border-accent cms-accent-soft cms-accent text-xs font-mono mb-6">
                <i class="fa-solid fa-bolt"></i><span>{$siteName}</span>
            </div>
            <h1 class="text-3xl md:text-5xl lg:text-6xl font-black tracking-tight cms-text mb-6 leading-tight">{$title}</h1>
            <p class="text-base md:text-lg cms-text-muted max-w-3xl mx-auto font-normal leading-relaxed">{$subtitle}</p>
            {$ctaButton}
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderText(array $content): string
    {
        $title = ! empty($content['title']) ? htmlspecialchars((string) $content['title']) : '';
        $raw = (string) ($content['content'] ?? '');
        $renderedHtml = $this->markdownRenderer->render($raw);

        $header = ! empty($title)
            ? "<h2 class=\"text-2xl font-bold cms-text mb-4 pb-2 border-b cms-border flex items-center gap-2\"><span class=\"cms-accent\">&gt;</span> {$title}</h2>"
            : '';

        return <<<HTML
        <div class="prose prose-invert max-w-none font-mono text-sm leading-relaxed cms-text-muted">
            {$header}
            {$renderedHtml}
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderFeatures(array $content): string
    {
        $title = htmlspecialchars((string) ($content['title'] ?? 'Características & Capacidades'));
        $items = (array) ($content['items'] ?? []);

        $cardsHtml = '';
        foreach ($items as $item) {
            $rawIcon = (string) ($item['icon'] ?? 'fa-solid fa-bolt');
            $iconHtml = str_contains($rawIcon, 'fa-') ? '<i class="'.htmlspecialchars($rawIcon).'"></i>' : htmlspecialchars($rawIcon);
            $fTitle = htmlspecialchars((string) ($item['title'] ?? 'Feature'));
            $fDesc = htmlspecialchars((string) ($item['desc'] ?? ''));

            $cardsHtml .= <<<HTML
            <div class="p-6 rounded-xl cms-surface-2 border cms-border cms-hover-border-accent transition-all group hover:-translate-y-1">
                <div class="text-3xl mb-4 p-3 inline-block rounded-lg cms-surface-2 border cms-border cms-hover-border-accent cms-accent">{$iconHtml}</div>
                <h3 class="text-lg font-bold cms-text mb-2 cms-hover-accent transition-colors">{$fTitle}</h3>
                <p class="text-xs cms-text-muted leading-relaxed">{$fDesc}</p>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="space-y-6">
            <div class="text-center max-w-2xl mx-auto mb-8">
                <h2 class="text-2xl md:text-3xl font-bold cms-text mb-2 flex items-center justify-center gap-2">
                    <span class="cms-accent">&gt;_</span> {$title}
                </h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {$cardsHtml}
            </div>
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderBeforeAfter(array $content): string
    {
        $title = htmlspecialchars((string) ($content['title'] ?? 'Comparador Before / After'));
        $beforeImg = htmlspecialchars((string) ($content['image_url'] ?? 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800'));
        $afterImg = htmlspecialchars((string) ($content['after_image_url'] ?? 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800'));

        return <<<HTML
        <div class="space-y-4" x-data="{ sliderPos: 50 }">
            <h2 class="text-xl font-bold cms-text flex items-center gap-2.5"><i class="fa-solid fa-scale-balanced cms-accent"></i> {$title}</h2>
            <div class="relative w-full h-80 sm:h-96 rounded-2xl overflow-hidden border cms-border select-none">
                <!-- After (Full background) -->
                <img src="{$afterImg}" alt="After" class="absolute inset-0 w-full h-full object-cover">
                <span class="absolute top-4 right-4 bg-black/80 cms-accent text-xs font-mono font-bold px-3 py-1 rounded-full border cms-border-accent z-10">MODERNO (dbCMS)</span>

                <!-- Before (Clipped overlay) -->
                <div class="absolute inset-0 overflow-hidden" :style="'width: ' + sliderPos + '%'">
                    <img src="{$beforeImg}" alt="Before" class="absolute inset-0 w-full h-full object-cover max-w-none" style="width: 100vw;">
                    <span class="absolute top-4 left-4 bg-black/80 cms-text-muted text-xs font-mono px-3 py-1 rounded-full border cms-border-strong z-10">LEGACY</span>
                </div>

                <!-- Slider Divider Line -->
                <div class="absolute top-0 bottom-0 w-1 cms-accent-bg cursor-ew-resize z-20 " :style="'left: ' + sliderPos + '%'">
                    <div class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-8 h-8 rounded-full cms-accent-bg text-black font-bold flex items-center justify-center text-xs shadow-lg">
                        &harr;
                    </div>
                </div>

                <!-- Interactive Range Input Overlay -->
                <input type="range" min="0" max="100" x-model="sliderPos" class="absolute inset-0 w-full h-full opacity-0 cursor-ew-resize z-30">
            </div>
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderGallery(array $content, ?Market $market = null, ?string $locale = null): string
    {
        if (! $this->isModuleActive('gallery') || ! class_exists(Gallery::class)) {
            return '<!-- gallery module inactive -->';
        }

        try {
            $galleries = Gallery::with(['items', 'translations'])->where('is_active', true)->take(6)->get();

            $itemsHtml = '';
            foreach ($galleries as $gallery) {
                $cover = $gallery->items->first();
                $img = $cover ? $cover->image_url : 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=600';
                $trans = $gallery->translations->where('locale', $locale ?? 'es')->first() ?? $gallery->translations->first();
                $gTitle = htmlspecialchars((string) ($trans->title ?? ($gallery->title ?? 'Galeria')));
                $gSlug = htmlspecialchars((string) ($trans->slug ?? ($gallery->slug ?? '')));
                $itemUrl = htmlspecialchars($this->localization->url("/galeria/{$gSlug}", $market, $locale));

                $itemsHtml .= <<<HTML
            <a href="{$itemUrl}" class="group relative rounded-xl overflow-hidden border cms-border cms-hover-border-accent transition-all aspect-video block bg-black">
                <img src="{$img}" alt="{$gTitle}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent flex items-end p-4">
                    <span class="cms-text font-bold text-sm cms-hover-accent transition-colors">{$gTitle}</span>
                </div>
            </a>
            HTML;
            }

            $allGalleriesUrl = htmlspecialchars($this->localization->url('/galeria', $market, $locale));
            $allGalleriesText = $locale === 'ca' ? 'Veure totes les galeries' : ($locale === 'en' ? 'View all galleries' : 'Ver todas las galerías');
            $galleryHeading = htmlspecialchars((string) ($content['title'] ?? ($locale === 'ca' ? 'Àlbums & Galeries' : ($locale === 'en' ? 'Albums & Galleries' : 'Álbumes & Galerías'))));

            return <<<HTML
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b cms-border">
                <h2 class="text-xl font-bold cms-text flex items-center gap-2.5"><i class="fa-solid fa-images cms-accent"></i> {$galleryHeading}</h2>
                <a href="{$allGalleriesUrl}" class="text-xs cms-accent hover:underline font-mono">{$allGalleriesText} &rarr;</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {$itemsHtml}
            </div>
        </div>
        HTML;
        } catch (\Throwable $e) {
            Log::warning("Gallery render error: {$e->getMessage()}");

            return '<!-- gallery silenced -->';
        }
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderFaq(array $content): string
    {
        $title = htmlspecialchars((string) ($content['title'] ?? 'Preguntas Frecuentes (FAQ)'));
        $items = (array) ($content['items'] ?? []);

        $faqHtml = '';
        foreach ($items as $idx => $item) {
            $q = htmlspecialchars((string) ($item['question'] ?? ''));
            $a = htmlspecialchars((string) ($item['answer'] ?? ''));

            $faqHtml .= <<<HTML
            <div class="border cms-border rounded-xl overflow-hidden cms-surface-2" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="w-full p-4 text-left flex items-center justify-between gap-4 cms-text font-bold cms-hover-accent transition-colors">
                    <span>{$q}</span>
                    <span class="cms-accent font-mono text-lg transition-transform" :class="open ? 'rotate-180' : ''">▾</span>
                </button>
                <div x-show="open" x-collapse class="p-4 pt-0 text-xs cms-text-muted leading-relaxed border-t cms-border/50 font-mono">
                    {$a}
                </div>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="space-y-4">
            <h2 class="text-2xl font-bold cms-text flex items-center gap-2.5"><i class="fa-solid fa-circle-question cms-accent"></i> {$title}</h2>
            <div class="space-y-3">
                {$faqHtml}
            </div>
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderCta(array $content, ?Market $market = null, ?string $locale = null): string
    {
        $defaultTitle = $locale === 'ca' ? 'A punt per escalar amb dbCMS?' : ($locale === 'en' ? 'Ready to scale with dbCMS?' : '¿Listo para escalar con dbCMS?');
        $defaultDesc = $locale === 'ca' ? 'Desplega arquitectura moderna, SEO d\'alta conversió i suport GEO natiu.' : ($locale === 'en' ? 'Deploy modern architecture, high-conversion SEO, and native GEO support.' : 'Despliega arquitectura moderna, SEO de alta conversión y soporte GEO nativo.');
        $defaultBtn = $locale === 'ca' ? 'Començar Ara' : ($locale === 'en' ? 'Get Started' : 'Empezar Ahora');

        $title = htmlspecialchars((string) ($content['title'] ?? $defaultTitle));
        $desc = htmlspecialchars((string) ($content['content'] ?? ($content['description'] ?? $defaultDesc)));
        $ctaText = htmlspecialchars((string) ($content['cta_text'] ?? ($content['cta_label'] ?? $defaultBtn)));
        $rawCtaUrl = (string) ($content['cta_url'] ?? ($content['cta_link'] ?? '/contacto'));

        $ctaUrl = $rawCtaUrl;
        if (str_starts_with($rawCtaUrl, '/')) {
            $ctaUrl = $this->localization->url($rawCtaUrl, $market, $locale);
        }
        $ctaUrl = htmlspecialchars($ctaUrl);

        return <<<HTML
        <div class="text-center p-8 md:p-12 rounded-2xl bg-gradient-to-r from-[#121212] via-[#1A1A1A] to-[#121212] border cms-border-accent ">
            <h2 class="text-2xl md:text-4xl font-black cms-text mb-4">{$title}</h2>
            <p class="text-sm md:text-base cms-text-muted max-w-2xl mx-auto mb-6">{$desc}</p>
            <a href="{$ctaUrl}" class="cms-btn cms-btn-primary text-sm py-3 px-8">{$ctaText} &rarr;</a>
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderCodeEmbed(array $content): string
    {
        $title = ! empty($content['title']) ? htmlspecialchars((string) $content['title']) : '';
        $rawCode = (string) ($content['content'] ?? '');

        $header = ! empty($title) ? "<div class=\"text-xs text-gray-500 font-bold uppercase mb-2\">{$title}</div>" : '';

        return <<<HTML
        <div class="w-full overflow-hidden my-4">
            {$header}
            {$rawCode}
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderWidgetPosts(array $content, ?Market $market = null, ?string $locale = null): string
    {
        if (! $this->isModuleActive('blog') || ! class_exists(Post::class)) {
            return '<!-- blog module inactive -->';
        }

        try {
            $title = htmlspecialchars((string) ($content['title'] ?? ($locale === 'ca' ? 'Últims Articles del Blog' : ($locale === 'en' ? 'Latest Blog Articles' : 'Últimos Artículos del Blog'))));
            $count = (int) ($content['post_count'] ?? 3);

            $posts = Post::with(['translations', 'category.translations'])->where('is_published', true)->latest()->take($count)->get();

            $postsHtml = '';
            foreach ($posts as $post) {
                $trans = $post->translations->where('locale', $locale ?? 'es')->first() ?? $post->translations->first();
                $pTitle = htmlspecialchars($trans->title ?? 'Post');
                $pSummary = htmlspecialchars($trans->summary ?? '');
                $pSlug = htmlspecialchars($trans->slug ?? '');
                $cover = $post->featured_image ?: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600';
                $postUrl = htmlspecialchars($this->localization->url("/blog/{$pSlug}", $market, $locale));
                $readMoreText = $locale === 'ca' ? 'Llegir Article' : ($locale === 'en' ? 'Read Article' : 'Leer Artículo');

                $postsHtml .= <<<HTML
                <div class="rounded-xl overflow-hidden cms-surface-2 border cms-border cms-hover-border-accent transition-all flex flex-col group">
                    <div class="h-44 bg-black overflow-hidden relative">
                        <img src="{$cover}" alt="{$pTitle}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold cms-text bg-black/70 backdrop-blur-sm rounded-md border border-white/10 font-mono">
                            <i class="fa-solid fa-newspaper cms-accent"></i>
                            <span>Blog</span>
                        </span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between space-y-3">
                        <div>
                            <h3 class="font-bold cms-text cms-hover-accent transition-colors text-base line-clamp-2">{$pTitle}</h3>
                            <p class="text-xs cms-text-muted mt-2 line-clamp-2 font-mono">{$pSummary}</p>
                        </div>
                        <a href="{$postUrl}" class="text-xs font-bold cms-accent hover:underline font-mono inline-flex items-center gap-1">
                            {$readMoreText} &rarr;
                        </a>
                    </div>
                </div>
                HTML;
            }

            $allPostsUrl = htmlspecialchars($this->localization->url('/blog', $market, $locale));
            $allPostsText = $locale === 'ca' ? 'Veure tots els articles' : ($locale === 'en' ? 'View all articles' : 'Ver todos los artículos');

            return <<<HTML
            <div class="space-y-6">
                <div class="flex items-center justify-between pb-2 border-b cms-border">
                    <h2 class="text-2xl font-bold cms-text flex items-center gap-2.5"><i class="fa-solid fa-newspaper cms-accent"></i> {$title}</h2>
                    <a href="{$allPostsUrl}" class="text-xs cms-accent hover:underline font-mono">{$allPostsText} &rarr;</a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {$postsHtml}
                </div>
            </div>
            HTML;
        } catch (\Throwable $e) {
            Log::warning("Widget posts render error: {$e->getMessage()}");

            return '<!-- blog posts silenced -->';
        }
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderWidgetForm(array $content, ?Market $market = null, ?string $locale = null): string
    {
        if (! $this->isModuleActive('forms')) {
            return '<!-- forms module inactive -->';
        }

        $defaultTitle = $locale === 'ca' ? 'Contacta amb Nosaltres' : ($locale === 'en' ? 'Contact Us' : 'Contacta con Nosotros');
        $title = htmlspecialchars((string) ($content['title'] ?? $defaultTitle));
        $slug = (string) ($content['form_slug'] ?? 'contacto');
        $actionUrl = htmlspecialchars($this->localization->url("/forms/{$slug}/submit", $market, $locale));

        $subtitle = $locale === 'ca' ? 'Envia la teva consulta i el nostre equip et respondrà al més aviat possible.' : ($locale === 'en' ? 'Submit your inquiry and our engineering team will get back to you promptly.' : 'Envía tu consulta y nuestro equipo te responderá a la brevedad.');
        $nameLabel = $locale === 'ca' ? 'Nom Complet *' : ($locale === 'en' ? 'Full Name *' : 'Nombre Completo *');
        $namePlaceholder = $locale === 'ca' ? 'El teu nom...' : ($locale === 'en' ? 'Your full name...' : 'Tu nombre...');
        $emailLabel = $locale === 'ca' ? 'Correu Electrònic *' : ($locale === 'en' ? 'Email Address *' : 'Correo Electrónico *');
        $messageLabel = $locale === 'ca' ? 'Missatge / Consulta *' : ($locale === 'en' ? 'Message / Inquiry *' : 'Mensaje / Consulta *');
        $messagePlaceholder = $locale === 'ca' ? 'Descriu el teu projecte o requeriment...' : ($locale === 'en' ? 'Describe your technical requirements...' : 'Describe tu proyecto o requerimiento...');
        $btnText = $locale === 'ca' ? 'Enviar Missatge' : ($locale === 'en' ? 'Send Message' : 'Enviar Mensaje');

        $csrf = csrf_field();

        return <<<HTML
        <div class="max-w-xl mx-auto p-6 md:p-8 rounded-2xl cms-surface-2 border cms-border">
            <h2 class="text-xl font-bold cms-text mb-2 flex items-center gap-2.5"><i class="fa-solid fa-paper-plane cms-accent"></i> {$title}</h2>
            <p class="text-xs cms-text-muted mb-6 font-mono">{$subtitle}</p>
            <form method="POST" action="{$actionUrl}" class="space-y-4 font-mono text-xs">
                {$csrf}
                <div>
                    <label class="block cms-text-muted text-[10px] uppercase font-bold mb-1">{$nameLabel}</label>
                    <input type="text" name="name" required placeholder="{$namePlaceholder}" class="w-full cms-surface-2 border cms-border-strong rounded-lg px-3 py-2 cms-text">
                </div>
                <div>
                    <label class="block cms-text-muted text-[10px] uppercase font-bold mb-1">{$emailLabel}</label>
                    <input type="email" name="email" required placeholder="tu@email.com" class="w-full cms-surface-2 border cms-border-strong rounded-lg px-3 py-2 cms-text">
                </div>
                <div>
                    <label class="block cms-text-muted text-[10px] uppercase font-bold mb-1">{$messageLabel}</label>
                    <textarea name="message" rows="4" required placeholder="{$messagePlaceholder}" class="w-full cms-surface-2 border cms-border-strong rounded-lg px-3 py-2 cms-text"></textarea>
                </div>
                <button type="submit" class="cms-btn cms-btn-primary w-full py-2.5">{$btnText} &rarr;</button>
            </form>
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderWidgetStats(array $content): string
    {
        $title = htmlspecialchars((string) ($content['title'] ?? 'Métricas Clave de Rendimiento'));
        $items = (array) ($content['items'] ?? []);

        $statsHtml = '';
        foreach ($items as $item) {
            $val = htmlspecialchars((string) ($item['value'] ?? '100%'));
            $lbl = htmlspecialchars((string) ($item['label'] ?? 'Métrica'));

            $statsHtml .= <<<HTML
            <div class="text-center p-6 rounded-xl cms-surface-2 border cms-border">
                <div class="text-3xl md:text-4xl font-black cms-accent font-mono mb-2">{$val}</div>
                <div class="text-xs cms-text-muted uppercase font-mono tracking-wider">{$lbl}</div>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="space-y-6">
            <h2 class="text-center text-2xl font-bold cms-text mb-6 flex items-center justify-center gap-2.5"><i class="fa-solid fa-chart-pie cms-accent"></i> {$title}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                {$statsHtml}
            </div>
        </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderWidgetProducts(array $content, ?Market $market = null, ?string $locale = null): string
    {
        if (! $this->isModuleActive('shop') || ! class_exists(FeaturedProductsWidget::class)) {
            return '<!-- shop module inactive -->';
        }

        try {
            $widget = app(FeaturedProductsWidget::class);
            $rendered = $widget->render($content);

            return is_string($rendered) ? $rendered : $rendered->render();
        } catch (\Throwable $e) {
            Log::warning("Widget products render error: {$e->getMessage()}");

            return '<!-- shop products silenced -->';
        }
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function renderWidget(string $widgetKey, array $content): string
    {
        if (empty($widgetKey)) {
            return '';
        }

        try {
            $prefix = explode('.', $widgetKey)[0] ?? '';
            if (! empty($prefix) && in_array($prefix, ['blog', 'shop', 'gallery', 'forms', 'charts', 'analytics'], true)) {
                if (! $this->isModuleActive($prefix)) {
                    return "<!-- widget {$widgetKey} silenced: module {$prefix} inactive -->";
                }
            }

            if (app()->bound("widget.{$widgetKey}")) {
                $widgetClass = app("widget.{$widgetKey}");
                if (is_string($widgetClass) && class_exists($widgetClass)) {
                    /** @var WidgetComponentInterface $instance */
                    $instance = app($widgetClass);
                    $rendered = $instance->render($content);

                    return is_string($rendered) ? $rendered : $rendered->render();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Widget {$widgetKey} failed to render: {$e->getMessage()}");

            return "<!-- widget {$widgetKey} silenced -->";
        }

        return "<!-- widget {$widgetKey} not found or inactive -->";
    }

    /**
     * Safely checks if a module is currently active.
     */
    protected function isModuleActive(string $identifier): bool
    {
        try {
            /** @var ModuleManager $manager */
            $manager = app(ModuleManager::class);

            return $manager->isActive($identifier);
        } catch (\Throwable) {
            return true;
        }
    }
}
