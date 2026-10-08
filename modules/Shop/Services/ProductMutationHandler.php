<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Content\Contracts\EntityMutationHandlerInterface;
use App\Core\Content\Contracts\EntityRelationMutationHandlerInterface;
use App\Core\Sections\SectionLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Shop\Models\Product;

final class ProductMutationHandler implements EntityMutationHandlerInterface, EntityRelationMutationHandlerInterface
{
    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $attributes = (array) ($args['attributes'] ?? []);
        $input = array_replace($attributes, array_intersect_key($args, array_flip(['translations', 'market_ids', 'market_prices', 'sections_json'])));
        Validator::make($input, ShopMutationRules::forEntity(true, $entity))->validate();
        $translations = (array) ($args['translations'] ?? []);
        $locale = app(LocalizationServiceInterface::class)->defaultLocale();
        $sections = $args['sections_json'] ?? [];
        if (is_string($sections)) {
            $sections = json_decode($sections, true);
        }
        if (array_key_exists('sections_json', $args) && ! is_array($sections)) {
            throw ValidationException::withMessages(['sections_json' => 'Sections must be a JSON object or array.']);
        }
        $sections = is_array($sections) ? $sections : [];
        if (array_is_list($sections)) {
            $sections = [$locale => $sections];
        }
        foreach ($translations as $code => $values) {
            if (! is_array($values)) {
                continue;
            }
            if (array_key_exists('title', $values) && ! array_key_exists('name', $values)) {
                $values['name'] = $values['title'];
            }
            unset($values['title']);
            if (isset($sections[$code]) && empty($values['description'])) {
                $chunks = [];
                foreach ((array) $sections[$code] as $section) {
                    foreach (is_array($section) ? SectionLayout::blocks($section) : [] as $block) {
                        $body = $block['content'] ?? $block['body'] ?? null;
                        if (is_string($body) && trim($body) !== '') {
                            $chunks[] = $body;
                        }
                    }
                }
                $values['description'] = implode("\n\n", $chunks);
            }
            if ($creating) {
                $values['summary'] ??= $translations[$locale]['summary'] ?? Str::limit(strip_tags((string) ($values['description'] ?? '')), 160);
                $values['description'] ??= $translations[$locale]['description'] ?? '';
                $values['meta_title'] ??= $values['name'] ?? '';
                $values['meta_description'] ??= $values['summary'];
                $values['schema_type'] ??= 'Product';
            }
            if (isset($values['slug']) && $values['slug'] !== '') {
                $values['slug'] = Str::slug((string) $values['slug']);
            }
            $translations[$code] = $values;
        }
        if ($creating && ! collect($translations)->contains(static fn (mixed $values): bool => is_array($values) && trim((string) ($values['name'] ?? '')) !== '')) {
            throw ValidationException::withMessages(['translations.name' => 'El nombre del producto es obligatorio.']);
        }
        if ($creating) {
            $attributes += ['price' => 0, 'stock_quantity' => 0, 'track_stock' => true, 'is_featured' => false, 'is_digital' => false, 'is_active' => false];
        }
        unset($attributes['market_prices']);

        return array_replace($args, ['attributes' => $attributes, 'translations' => $translations, 'market_prices' => (array) ($input['market_prices'] ?? [])]);
    }

    public function syncRelations(Model $entity, array $args): void
    {
        assert($entity instanceof Product);
        if (! array_key_exists('market_ids', $args) && ($args['market_prices'] ?? []) === []) {
            return;
        }
        $prices = (array) ($args['market_prices'] ?? []);
        $existing = $entity->markets()->get()->keyBy('id');
        $ids = array_key_exists('market_ids', $args) ? (array) $args['market_ids'] : $existing->modelKeys();
        foreach (array_keys($prices) as $id) {
            if (! in_array((int) $id, array_map('intval', $ids), true)) {
                if ($prices[$id] === null || $prices[$id] === '') {
                    unset($prices[$id]);

                    continue;
                }
                throw ValidationException::withMessages(['market_prices' => 'Custom prices must belong to selected markets.']);
            }
        }
        $sync = [];
        foreach ($ids as $id) {
            $pivot = $existing->get((int) $id)?->getRelation('pivot');
            $sync[(int) $id] = [
                'custom_price' => array_key_exists($id, $prices) ? $prices[$id] : ($pivot instanceof Model ? $pivot->getAttribute('custom_price') : null),
                'is_active' => $pivot instanceof Model ? $pivot->getAttribute('is_active') : true,
            ];
        }
        $entity->markets()->sync($sync);
    }
}
