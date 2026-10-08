<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class EntityMutationData
{
    /** @param array<string,mixed> $arguments */
    public function __construct(public array $arguments) {}

    /** @param array<string,mixed> $input */
    public static function fromLegacy(array $input): self
    {
        $attributes = $input;
        unset($attributes['translations'], $attributes['market_ids'], $attributes['sections_json'], $attributes['sections'], $attributes['user_id'], $attributes['audit_action'], $attributes['audit_notes']);
        $args = ['attributes' => $attributes, 'translations' => (array) ($input['translations'] ?? [])];
        if (in_array($input['audit_action'] ?? null, ['draft', 'submit_for_review'], true)) {
            $args['audit_action'] = $input['audit_action'];
            $args['audit_notes'] = is_string($input['audit_notes'] ?? null) ? $input['audit_notes'] : null;
        }
        if (array_key_exists('market_ids', $input)) {
            $args['market_ids'] = array_values(array_map('intval', (array) $input['market_ids']));
        }
        if (is_string($input['sections_json'] ?? null)) {
            $args['sections_json'] = $input['sections_json'];
        } elseif (isset($input['sections'])) {
            $byLocale = [];
            $sections = array_values((array) $input['sections']);
            usort($sections, static fn (mixed $a, mixed $b): int => ((is_array($a) ? $a['order'] ?? 0 : 0) <=> (is_array($b) ? $b['order'] ?? 0 : 0)));
            foreach ($sections as $section) {
                if (! is_array($section)) {
                    continue;
                }
                foreach ((array) ($section['translations'] ?? []) as $locale => $content) {
                    $byLocale[$locale][] = ['type' => $section['type'] ?? 'text', 'widget_key' => $section['widget_key'] ?? null] + (array) ($section['styling_json'] ?? []) + (array) $content;
                }
            }
            $args['sections_json'] = json_encode($byLocale, JSON_THROW_ON_ERROR);
        }

        return new self($args);
    }
}
