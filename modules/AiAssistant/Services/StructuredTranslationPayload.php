<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use Modules\AiAssistant\DTOs\TranslationSnapshot;
use UnexpectedValueException;

/** Extracts text segments and restores their translations into an untouched copy. */
final class StructuredTranslationPayload
{
    public function __construct(private readonly BlockRegistryInterface $blocks) {}

    /**
     * @param  array<string,mixed>  $fields
     * @param  list<array<string,mixed>>  $sections
     * @param  list<string>  $roles
     */
    public function extract(array $fields, array $sections, array $roles): TranslationSnapshot
    {
        /** @var list<array{id:string,text:string}> $segments */
        $segments = [];
        /** @var list<array{path:list<int|string>,parts:list<array{literal?:string,id?:string}>}> $templates */
        $templates = [];
        $protected = [];
        foreach ($roles as $role) {
            if (isset($fields[$role]) && is_string($fields[$role])) {
                $this->text($fields[$role], ['fields', $role], $segments, $templates);
            }
        }
        foreach ($sections as $index => $section) {
            $this->block($section, ['sections', $index], $segments, $templates, $protected);
        }

        return new TranslationSnapshot(['fields' => $fields, 'sections' => $sections], $segments, $templates, array_values(array_unique($protected)));
    }

    /**
     * @param  array<mixed>  $translated
     * @return array<string,mixed>
     */
    public function restore(TranslationSnapshot $snapshot, array $translated): array
    {
        if (! array_is_list($translated) || count($translated) !== count($snapshot->segments)) {
            throw new UnexpectedValueException('Translation segments do not match the source.');
        }
        $values = [];
        foreach ($translated as $segment) {
            if (! is_array($segment) || count($segment) !== 2 || ! array_key_exists('id', $segment) || ! array_key_exists('text', $segment) || ! is_string($segment['id']) || ! is_string($segment['text']) || isset($values[$segment['id']])
                || preg_match('/[<>\r\n`\[\]*_]|https?:\/\//u', $segment['text']) || trim($segment['text']) === '') {
                throw new UnexpectedValueException('Invalid translated text segment.');
            }
            $values[$segment['id']] = $segment['text'];
        }
        foreach ($snapshot->segments as $segment) {
            if (! array_key_exists($segment['id'], $values)) {
                throw new UnexpectedValueException('A translation segment is missing.');
            }
        }
        $result = $snapshot->original;
        foreach ($snapshot->templates as $template) {
            $text = '';
            foreach ($template['parts'] as $part) {
                $text .= $part['literal'] ?? $values[$part['id'] ?? ''];
            }
            $result = $this->replace($result, $template['path'], $text);
        }

        /** @var array<string,mixed> $result */
        return $result;
    }

    /**
     * @param  array<string|int,mixed>  $tree
     * @param  list<int|string>  $path
     * @return array<string|int,mixed>
     */
    private function replace(array $tree, array $path, string $text): array
    {
        $key = array_shift($path);
        if ($key === null) {
            throw new UnexpectedValueException('Missing source path.');
        }
        if ($path === []) {
            $tree[$key] = $text;
        } else {
            $tree[$key] = $this->replace((array) $tree[$key], $path, $text);
        }

        return $tree;
    }

    /**
     * @param  array<string,mixed>  $block
     * @param  list<int|string>  $path
     * @param  list<array{id:string,text:string}>  $segments
     * @param  list<array{path:list<int|string>,parts:list<array{literal?:string,id?:string}>}>  $templates
     * @param  list<string>  $protected
     */
    private function block(array $block, array $path, array &$segments, array &$templates, array &$protected): void
    {
        $type = (string) ($block['type'] ?? '');
        $definition = $this->blocks->getActiveBlocks()[$type] ?? null;
        $paths = $definition instanceof TranslatableBlockInterface ? $definition->getTranslationPaths() : null;
        if ($paths === null && $type !== '') {
            $protected[] = $type;
        }
        foreach ($paths ?? [] as $field) {
            $this->field($block, explode('.', $field), $path, $segments, $templates);
        }
        foreach ((array) ($block['columns'] ?? []) as $columnIndex => $column) {
            if (! is_array($column)) {
                continue;
            }
            foreach ((array) ($column['blocks'] ?? []) as $blockIndex => $child) {
                if (is_array($child)) {
                    $this->block($child, [...$path, 'columns', $columnIndex, 'blocks', $blockIndex], $segments, $templates, $protected);
                }
            }
        }
    }

    /**
     * @param  list<string>  $parts
     * @param  list<int|string>  $path
     * @param  list<array{id:string,text:string}>  $segments
     * @param  list<array{path:list<int|string>,parts:list<array{literal?:string,id?:string}>}>  $templates
     */
    private function field(mixed $value, array $parts, array $path, array &$segments, array &$templates): void
    {
        if ($parts === []) {
            if (is_string($value)) {
                $this->text($value, $path, $segments, $templates);
            }

            return;
        }
        if (! is_array($value)) {
            return;
        }
        $key = array_shift($parts);
        foreach ($value as $childKey => $child) {
            if ($key === '*' || (string) $childKey === $key) {
                $this->field($child, $parts, [...$path, $childKey], $segments, $templates);
            }
        }
    }

    /**
     * @param  list<int|string>  $path
     * @param  list<array{id:string,text:string}>  $segments
     * @param  list<array{path:list<int|string>,parts:list<array{literal?:string,id?:string}>}>  $templates
     */
    private function text(string $text, array $path, array &$segments, array &$templates): void
    {
        // Protect code, HTML syntax/attributes, Markdown syntax and destinations.
        $pattern = <<<'REGEX'
~(<(?:script|style|pre|code)\b[^>]*>.*?</(?:script|style|pre|code)\s*>|<!--[\s\S]*?-->|<(?:[^\"'<>]|\"[^\"]*\"|'[^']*')*>|```[\s\S]*?```|\~\~\~[\s\S]*?\~\~\~|`[^`]*`|!\[[^\]]*\]\([^)]*\)|\]\([^)]*\)|\]\[[^\]]*\]|https?://[^\s<>]+|\{\{.*?\}\}|\$\{.*?\}|&(?:\#\d+|\#x[0-9a-f]+|[a-z]+);|\\\\[\s\S]|[\r\n]+|[\#*_\[\]>|]+|^[ \t]*(?:[-+] |\d+\. ))~isum
REGEX;
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            throw new UnexpectedValueException('Invalid source text.');
        }
        $template = [];
        foreach ($parts as $part) {
            if (preg_match($pattern, $part) === 1 || ! preg_match('/\p{L}/u', $part)) {
                $template[] = ['literal' => $part];

                continue;
            }
            preg_match('/^(\s*)(.*?)(\s*)$/su', $part, $matches);
            if (($matches[1] ?? '') !== '') {
                $template[] = ['literal' => $matches[1]];
            }
            $id = 't'.count($segments);
            $segments[] = ['id' => $id, 'text' => $matches[2] ?? $part];
            $template[] = ['id' => $id];
            if (($matches[3] ?? '') !== '') {
                $template[] = ['literal' => $matches[3]];
            }
        }
        $templates[] = ['path' => $path, 'parts' => $template];
    }
}
