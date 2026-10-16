<?php

declare(strict_types=1);

namespace App\Core\Blocks;

use App\Core\Blocks\Contracts\BlockTypeInterface;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use App\Services\Cms\FrontViewResolver;
use Illuminate\Support\Str;

abstract class AbstractBlockType implements BlockTypeInterface, TranslatableBlockInterface
{
    public function getRequiredModule(): ?string
    {
        return null;
    }

    public function isDynamic(): bool
    {
        return $this->getRequiredModule() !== null;
    }

    public function isFullWidth(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return '';
    }

    public function getEditorSchema(): array
    {
        return $this->schemaFor($this->getDefaultPayload());
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,array<string,mixed>>
     */
    private function schemaFor(array $payload): array
    {
        $fields = [];
        foreach ($payload as $key => $value) {
            $field = [
                'type' => match (true) {
                    is_array($value) && array_is_list($value) && is_array($value[0] ?? null) => 'repeater',
                    is_array($value) && array_is_list($value) => 'list',
                    is_array($value) => 'json',
                    is_bool($value) => 'boolean',
                    is_int($value), is_float($value) => 'number',
                    default => str_contains($key, 'content') || str_contains($key, 'description') || $key === 'answer' ? 'textarea' : 'text',
                },
                'label' => Str::headline($key),
            ];
            if ($field['type'] === 'repeater') {
                $field['fields'] = $this->schemaFor($value[0]);
            }
            $fields[$key] = $field;
        }

        return $fields;
    }

    public function getEditorView(): ?string
    {
        return null;
    }

    public function getEditorData(): array
    {
        return [];
    }

    public function getTranslationPaths(): array
    {
        return [];
    }

    /**
     * Renders a Blade view checking theme override first, then fallback to base component.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderView(string $viewName, array $data = []): string
    {
        $resolved = app(FrontViewResolver::class)->block($viewName);

        if ($resolved !== null) {
            return view($resolved, $data)->render();
        }

        return "<!-- View [{$viewName}] not found -->";
    }
}
