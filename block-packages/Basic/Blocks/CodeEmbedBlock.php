<?php

declare(strict_types=1);

namespace BlockPackages\Basic\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;

class CodeEmbedBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title'];
    }

    public function getType(): string
    {
        return 'code_embed';
    }

    public function getName(): string
    {
        return 'Incrustador de Código / HTML';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-code';
    }

    public function getCategory(): string
    {
        return 'interactive';
    }

    public function getDescription(): string
    {
        return 'Incrustación de fragmentos HTML, iframes, widgets de terceros o scripts seguros.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => '',
            'content' => '<div class="p-4 cms-surface-2 border cms-border rounded-lg text-center font-mono text-xs cms-text-muted">Contenido HTML incrustado</div>',
        ];
    }

    public function getEditorSchema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Título opcional', 'description' => 'Etiqueta pequeña sobre el contenido incrustado.', 'rules' => ['nullable', 'string', 'max:255']],
            'content' => ['type' => 'textarea', 'label' => 'HTML incrustado', 'description' => 'Se renderiza dentro de un iframe con sandbox. Revisa el código antes de publicar.', 'rules' => ['nullable', 'string']],
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = ! empty($payload['title']) ? htmlspecialchars((string) $payload['title']) : '';
        $rawCode = htmlspecialchars((string) ($payload['content'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $header = ! empty($title) ? "<div class=\"text-xs text-gray-500 font-bold uppercase mb-2 font-mono\">{$title}</div>" : '';

        return <<<HTML
        <div class="w-full overflow-hidden my-4">
            {$header}
            <iframe class="w-full min-h-80 border-0" title="{$title}" sandbox="allow-scripts" referrerpolicy="no-referrer" srcdoc="{$rawCode}"></iframe>
        </div>
        HTML;
    }
}
