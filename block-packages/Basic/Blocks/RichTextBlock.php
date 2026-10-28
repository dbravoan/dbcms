<?php

declare(strict_types=1);

namespace BlockPackages\Basic\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;
use App\Services\Media\MarkdownRendererService;

class RichTextBlock extends AbstractBlockType
{
    public function __construct(
        protected ?MarkdownRendererService $markdown = null
    ) {
        $this->markdown = $markdown ?? app(MarkdownRendererService::class);
    }

    public function getTranslationPaths(): array
    {
        return ['title', 'content'];
    }

    public function getType(): string
    {
        return 'text';
    }

    public function getName(): string
    {
        return 'Texto Enriquecido (WYSIWYG)';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-file-lines';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getDescription(): string
    {
        return 'Bloque de texto formateado con soporte para negrita, encabezados, listas, citas, código e imágenes.';
    }

    public function getEditorView(): ?string
    {
        return 'basic::editor.rich-text';
    }

    public function getEditorSchema(): array
    {
        $fields = parent::getEditorSchema();
        $fields['format'] = [
            'type' => 'select',
            'label' => 'Formato del contenido',
            'options' => ['markdown' => 'Markdown', 'html' => 'HTML saneado'],
            'default' => 'markdown',
            'description' => 'El editor muestra las mismas opciones en su barra de herramientas.',
        ];

        return $fields;
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Sección de Contenido Técnico',
            'content' => "## Rendimiento y Escalabilidad Perimetral\n\nNuestra arquitectura garantiza una latencia **sub-50ms** en todo el mundo, integrando microservicios modulares y sincronización multi-mercado.",
            'format' => 'markdown',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $rawContent = (string) ($payload['content'] ?? '');
        $format = (string) ($payload['format'] ?? 'markdown');

        if ($format === 'html') {
            $renderedHtml = $this->markdown ? $this->markdown->renderHtml($rawContent) : $rawContent;
        } else {
            $renderedHtml = $this->markdown ? $this->markdown->render($rawContent) : $rawContent;
        }

        $titleHtml = ! empty($title) ? "<h2 class=\"text-2xl font-bold cms-text mb-6 flex items-center gap-2\"><span class=\"cms-accent\">&gt;</span> {$title}</h2>" : '';

        return <<<HTML
        <div class="prose prose-invert max-w-none font-mono text-xs sm:text-sm cms-text-muted leading-relaxed">
            {$titleHtml}
            {$renderedHtml}
        </div>
        HTML;
    }
}
