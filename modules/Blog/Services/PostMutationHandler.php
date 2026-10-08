<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Content\Contracts\EntityMutationHandlerInterface;
use App\Core\Sections\SectionLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PostMutationHandler implements EntityMutationHandlerInterface
{
    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $translations = (array) ($args['translations'] ?? []);
        $attributes = (array) ($args['attributes'] ?? []);
        if ($creating) {
            $attributes += ['allow_comments' => true, 'is_featured' => false];
        }
        $rawSections = $args['sections_json'] ?? [];
        $decoded = is_array($rawSections)
            ? $rawSections
            : (is_string($rawSections) ? json_decode($rawSections, true) : null);
        $sections = is_array($decoded) ? $decoded : [];
        if (array_key_exists('sections_json', $args) && array_is_list($sections)) {
            $sections = [app(LocalizationServiceInterface::class)->defaultLocale() => $sections];
        }
        foreach ($translations as $locale => $values) {
            if (! is_array($values)) {
                continue;
            }
            if (empty($values['content']) && isset($sections[$locale])) {
                $chunks = [];
                foreach ((array) $sections[$locale] as $section) {
                    if (! is_array($section)) {
                        continue;
                    }
                    foreach (SectionLayout::blocks($section) as $block) {
                        $content = $block['content'] ?? $block['body'] ?? null;
                        if (is_string($content) && trim($content) !== '') {
                            $chunks[] = $content;
                        }
                    }
                }
                $values['content'] = implode("\n\n", $chunks);
            }
            if (array_key_exists('content', $values)) {
                $values['summary'] ??= Str::limit(strip_tags((string) $values['content']), 160);
            }
            if ($creating || array_key_exists('title', $values)) {
                $values['meta_title'] ??= $values['title'] ?? '';
            }
            if ($creating || array_key_exists('summary', $values)) {
                $values['meta_description'] ??= $values['summary'] ?? '';
            }
            if ($creating) {
                $values['schema_type'] ??= 'BlogPosting';
            }
            $translations[$locale] = $values;
        }
        $first = reset($translations);
        if (is_array($first) && array_key_exists('content', $first)) {
            $attributes['reading_time_minutes'] = max(1, (int) ceil(str_word_count(strip_tags((string) $first['content'])) / 200));
        }
        if (($attributes['is_published'] ?? $attributes['publish'] ?? false) && ! isset($attributes['published_at'])) {
            $attributes['published_at'] = $entity->getAttribute('published_at') ?? now();
        }

        return array_replace($args, ['attributes' => $attributes, 'translations' => $translations]);
    }
}
