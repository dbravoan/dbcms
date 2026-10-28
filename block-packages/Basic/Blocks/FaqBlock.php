<?php

declare(strict_types=1);

namespace BlockPackages\Basic\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;

class FaqBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'items.*.question', 'items.*.answer'];
    }

    public function getType(): string
    {
        return 'faq';
    }

    public function getName(): string
    {
        return 'Preguntas Frecuentes (FAQ)';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-circle-question';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getDescription(): string
    {
        return 'Acordeón desplegable de preguntas y respuestas frecuentes con marcado semántico.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Preguntas Frecuentes',
            'items' => [
                ['question' => '¿Cómo funciona el despliegue multi-mercado?', 'answer' => config('cms.name').' permite asociar contenidos a mercados específicos sin duplicar código ni bases de datos.'],
                ['question' => '¿Qué ventajas aporta la optimización GEO?', 'answer' => 'Genera esquemas semánticos Schema.org y archivos llms.txt para maximizar la citabilidad en Perplexity, ChatGPT y Claude.'],
            ],
        ];
    }

    public function getEditorSchema(): array
    {
        $schema = parent::getEditorSchema();
        $schema['title']['description'] = 'Título opcional del acordeón.';
        $schema['items']['label'] = 'Preguntas';
        $schema['items']['description'] = 'Cada fila es una pregunta con su respuesta desplegable.';
        $schema['items']['fields']['question']['label'] = 'Pregunta';
        $schema['items']['fields']['answer']['label'] = 'Respuesta';
        $schema['items']['fields']['answer']['description'] = 'Admite texto plano y enlaces markdown según el tema.';

        return $schema;
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? 'Preguntas Frecuentes'));
        $items = (array) ($payload['items'] ?? []);

        $faqHtml = '';
        foreach ($items as $item) {
            $q = htmlspecialchars((string) ($item['question'] ?? ''));
            $a = (string) ($item['answer'] ?? '');

            $faqHtml .= <<<HTML
            <div class="border cms-border rounded-xl overflow-hidden cms-surface-2" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cms-text font-bold cms-hover-accent transition-colors">
                    <span class="text-sm">{$q}</span>
                    <span class="cms-accent font-mono text-lg transition-transform duration-300" :class="open ? 'rotate-180' : ''">▾</span>
                </button>
                <div x-show="open" x-collapse class="p-5 pt-0 text-xs cms-text-muted leading-relaxed border-t cms-border/50 font-mono">
                    {$a}
                </div>
            </div>
            HTML;
        }

        $headerHtml = ! empty($title) ? "<h2 class=\"text-2xl font-bold cms-text flex items-center gap-2 mb-6\"><span class=\"cms-accent\">&gt;</span> {$title}</h2>" : '';

        return <<<HTML
        <div class="space-y-4 font-mono w-full max-w-4xl mx-auto">
            {$headerHtml}
            <div class="space-y-3">
                {$faqHtml}
            </div>
        </div>
        HTML;
    }
}
