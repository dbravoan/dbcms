<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use App\Core\Content\Contracts\EntityMutationHandlerInterface;
use Illuminate\Database\Eloquent\Model;

final class PageMutationHandler implements EntityMutationHandlerInterface
{
    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $attributes = (array) ($args['attributes'] ?? []);
        $translations = (array) ($args['translations'] ?? []);
        if ($creating) {
            $attributes += ['template' => 'default', 'layout' => 'app'];
        }
        foreach ($translations as $locale => $values) {
            if (! is_array($values)) {
                continue;
            }
            if ($creating || array_key_exists('title', $values)) {
                $values['meta_title'] ??= $values['title'] ?? '';
            }
            if ($creating) {
                $values['schema_type'] ??= 'WebPage';
            }
            $translations[$locale] = $values;
        }
        if (($attributes['is_published'] ?? $attributes['publish'] ?? false) && ! isset($attributes['published_at'])) {
            $attributes['published_at'] = $entity->getAttribute('published_at') ?? now();
        }

        return array_replace($args, ['attributes' => $attributes, 'translations' => $translations]);
    }
}
