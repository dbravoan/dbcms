<?php

declare(strict_types=1);

namespace App\Core\Blocks;

use App\Core\Blocks\Contracts\BlockTypeInterface;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use Illuminate\Support\Str;

abstract class AbstractBlockType implements BlockTypeInterface, TranslatableBlockInterface
{
    public function getRequiredModule(): ?string
    {
        return null;
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
                    $key === 'url' || str_ends_with($key, '_url') => 'url',
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
}
