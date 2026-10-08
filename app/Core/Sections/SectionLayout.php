<?php

declare(strict_types=1);

namespace App\Core\Sections;

use App\Models\Language;

/**
 * The canonical contract of a block-manager section.
 *
 * A **section** is a container (column layout + row styling + animation); inside
 * it live 1..N **columns**, and inside each column live 0..N **blocks** — and a
 * block is exactly what the block registry offers (text, hero, features… or a
 * module widget: blog, shop, gallery, form, charts). Nothing in a section knows
 * which module produced a block, which is what keeps the editor, the renderer,
 * the MCP surface and the AI assistant working on the very same shape.
 *
 * ```
 * section
 *  ├── columns: [ { id, blocks: [ {…block payload…} ] } ]   ← canonical key
 *  └── one flat key per column, each with its own blocks
 * ```
 *
 * Content resolution is **single-sourced** here: for every field, the localized
 * `content_json` wins over the `styling_json` master. Before this class, the
 * master (usually Spanish) won, which is why an English page rendered Spanish
 * text inside multi-column sections.
 *
 * The class never mutates the database: it is a pure normalizer shared by the
 * renderer, the editor payload, the sync, MCP, the AI bridge and the seeders.
 */
final class SectionLayout
{
    /** Canonical key holding the columns of a section. */
    public const COLUMNS = 'columns';

    /** Container key a block uses for its own interior CSS grid (features, pricing…). */
    public const GRID_COLUMNS = 'grid_columns';

    /** How many columns a layout describes. */
    public static function columnCount(string $layout): int
    {
        return match ($layout) {
            '2', '1-2', '2-1' => 2,
            '3' => 3,
            '4' => 4,
            default => 1,
        };
    }

    /**
     * Container CSS grid of a block: the canonical `grid_columns` key.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function gridColumns(array $payload, string $default = '3'): string
    {
        $value = $payload[self::GRID_COLUMNS] ?? null;

        if (is_numeric($value) && (int) $value >= 1) {
            return (string) (int) $value;
        }

        return $default;
    }

    /**
     * Normalizes a section payload into the canonical shape:
     *
     * - `columns` is always a list of `{id, blocks, style}`.
     * - A section with content but no columns becomes one column holding its own
     *   content, so every section renders through the same path.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalize(array $payload): array
    {
        $columns = self::rawColumns($payload);

        if ($columns !== []) {
            unset($payload[self::COLUMNS]);
            $payload[self::COLUMNS] = self::normalizeColumns($columns, $payload);

            return $payload;
        }

        if (array_key_exists(self::COLUMNS, $payload)) {
            unset($payload[self::COLUMNS]);

            if (self::hasContent($payload)) {
                // A section with content but no columns: its own content becomes
                // the first column, so every section renders through one path.
                $payload[self::COLUMNS] = [[
                    'id' => self::columnId(0),
                    'blocks' => [self::sectionAsBlock($payload)],
                ]];
            }
        }

        return $payload;
    }

    /**
     * Locale-aware payload: the localized content of the locale wins over the
     * master payload, field by field, for every level (section and blocks).
     *
     * @param  array<string, mixed>  $master  styling_json (defaults + row styling)
     * @param  array<string, mixed>  $content  content_json for one locale
     * @return array<string, mixed>
     */
    public static function resolve(array $master, array $content): array
    {
        $merged = array_replace($master, $content);

        // Columns are always normalized after merging: the master may carry the
        // layout and the locale the block content (or the other way around).
        return self::normalize($merged);
    }

    /**
     * A payload for the given locale, resolved from an already loaded section.
     *
     * @param  array<string, mixed>  $master
     * @param  array<string, array<string, mixed>>  $contentByLocale
     * @return array<string, mixed>
     */
    public static function forLocale(array $master, array $contentByLocale, string $locale): array
    {
        $content = $contentByLocale[$locale] ?? [];

        return self::resolve($master, is_array($content) ? $content : []);
    }

    /**
     * The columns of a payload, normalized and guaranteed to be a list.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{id: string, blocks: list<array<string, mixed>>}>
     */
    public static function columns(array $payload): array
    {
        $columns = self::rawColumns($payload);

        if ($columns === []) {
            return [];
        }

        return self::normalizeColumns($columns, $payload, includeSelf: false);
    }

    /**
     * Every block of the section, flattened in visual order (columns left to
     * right, blocks top to bottom). When the section has no columns, the section
     * itself is its own single block, which keeps legacy content renderable.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function blocks(array $payload): array
    {
        $columns = self::columns($payload);

        if ($columns === []) {
            return [$payload];
        }

        $blocks = [];

        foreach ($columns as $column) {
            foreach ($column['blocks'] as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Plain text of every block (GEO/SEO surfaces, diffs, inspectors).
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    public static function plainText(array $payload, array $keys = ['title', 'headline', 'content', 'body', 'summary', 'subtitle']): string
    {
        $chunks = [];

        foreach (self::blocks($payload) as $block) {
            foreach ($keys as $key) {
                $value = $block[$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $chunks[] = trim(strip_tags($value));
                }
            }

            // Repeatable content (features, faq, pricing…) lives in `items`.
            foreach ((array) ($block['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach ($keys as $key) {
                    $value = $item[$key] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        $chunks[] = trim(strip_tags($value));
                    }
                }
            }

            // Carousel slides carry their own headlines.
            foreach ((array) ($block['slides'] ?? []) as $slide) {
                if (! is_array($slide)) {
                    continue;
                }

                foreach (['title', 'subtitle'] as $key) {
                    $value = $slide[$key] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        $chunks[] = trim(strip_tags($value));
                    }
                }
            }
        }

        return implode("\n\n", array_values(array_unique($chunks)));
    }

    /**
     * Stacks a layout back to a single column without losing anything.
     *
     * All blocks are flattened in visual order into one column (the user's rule:
     * going back to one column piles the content up), keeping `columns` present
     * so nothing is destroyed and the payload stays canonical.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function stack(array $payload): array
    {
        $blocks = self::blocks($payload);

        $payload = self::normalize($payload);
        $payload['columns_layout'] = '1';

        if (self::hasOwnColumns($payload)) {
            $payload[self::COLUMNS] = [[
                'id' => self::columnId(0),
                'blocks' => $blocks,
            ]];
        }

        return $payload;
    }

    /**
     * Resizes the column list, moving the section content into the first column
     * when a multi-column layout starts, and restoring (or creating) the columns
     * needed to reach the target count.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function withLayout(array $payload, string $layout): array
    {
        $count = self::columnCount($layout);

        if ($count <= 1) {
            return self::stack($payload);
        }

        $payload = self::normalize($payload);

        // A section that still renders its own content starts a column list with
        // that content on the way in: the section's block becomes the first one.
        if (! self::hasOwnColumns($payload)) {
            $payload[self::COLUMNS] = [[
                'id' => self::columnId(0),
                'blocks' => [self::sectionAsBlock($payload)],
            ]];
        }

        $columns = self::columns($payload);

        while (count($columns) > $count) {
            // Extra columns are dropped, their blocks kept in the last one so no
            // content is silently destroyed.
            $removed = array_pop($columns);
            $columns[count($columns) - 1]['blocks'] = array_merge(
                $columns[count($columns) - 1]['blocks'],
                $removed['blocks'] ?? [],
            );
        }

        while (count($columns) < $count) {
            $columns[] = ['id' => self::columnId(count($columns)), 'blocks' => []];
        }

        $payload['columns_layout'] = $layout;
        $payload[self::COLUMNS] = $columns;

        return $payload;
    }

    /**
     * Active locales of the installation (editor payloads).
     *
     * @return list<string>
     */
    public static function activeLocales(): array
    {
        $locales = Language::query()->where('is_active', true)->orderBy('id')->pluck('code')->all();

        if ($locales === []) {
            return ['es'];
        }

        return array_values(array_map('strval', $locales));
    }

    /**
     * Raw column list of a payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    public static function rawColumns(array $payload): array
    {
        $candidate = $payload[self::COLUMNS] ?? null;

        if (! is_array($candidate) || $candidate === []) {
            return [];
        }

        // A list of columns is either numeric-keyed or holds `blocks`/`id` keys.
        if (array_is_list($candidate)) {
            return $candidate;
        }

        return array_key_exists('blocks', $candidate) ? [$candidate] : [];
    }

    /**
     * @param  array<mixed>  $columns
     * @param  array<string, mixed>  $payload
     * @return list<array{id: string, blocks: list<array<string, mixed>>}>
     */
    private static function normalizeColumns(array $columns, array $payload, bool $includeSelf = true): array
    {
        $normalized = [];

        foreach ($columns as $index => $column) {
            if (! is_array($column)) {
                continue;
            }

            $blocks = [];

            foreach ((array) ($column['blocks'] ?? []) as $block) {
                if (is_array($block)) {
                    $blocks[] = self::normalize($block);
                }
            }

            $normalized[] = [
                'id' => (string) ($column['id'] ?? self::columnId((int) $index)),
                'blocks' => $blocks,
                'width' => $column['width'] ?? null,
                // Column styling (background, borders, padding, bg behaviour):
                // preserved so the appearance modal's `Column` scope round-trips.
                'style' => is_array($column['style'] ?? null) ? $column['style'] : [],
            ];
        }

        // No columns declared: the section's own content IS the first column,
        // which is what makes legacy payloads render through a single path.
        if ($normalized === [] && $includeSelf && self::hasContent($payload)) {
            $normalized[] = [
                'id' => self::columnId(0),
                'blocks' => [self::sectionAsBlock($payload)],
            ];
        }

        return $normalized;
    }

    /**
     * True when the payload declares a column list (even an empty one) — the
     * difference between "no columns yet" and "one column with the section".
     *
     * @param  array<string, mixed>  $payload
     */
    private static function hasOwnColumns(array $payload): bool
    {
        return array_key_exists(self::COLUMNS, $payload);
    }

    /**
     * The section seen as a block, so it can live inside a column.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function sectionAsBlock(array $payload): array
    {
        $block = $payload;

        foreach ([self::COLUMNS, 'columns_layout', 'id'] as $containerKey) {
            unset($block[$containerKey]);
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function hasContent(array $payload): bool
    {
        foreach (['title', 'headline', 'content', 'body', 'summary', 'subtitle', 'widget_key', 'items'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return true;
            }

            if (is_array($value) && $value !== []) {
                return true;
            }
        }

        return false;
    }

    private static function columnId(int $index): string
    {
        return 'col_'.$index.'_'.substr(md5((string) $index.microtime(true)), 0, 4);
    }
}
