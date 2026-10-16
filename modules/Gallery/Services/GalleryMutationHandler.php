<?php

declare(strict_types=1);

namespace Modules\Gallery\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Content\Contracts\EntityMutationHandlerInterface;
use App\Core\Content\Contracts\EntityRelationMutationHandlerInterface;
use App\Core\Content\Contracts\EntityRevisionHandlerInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Models\GalleryItem;

final class GalleryMutationHandler implements EntityMutationHandlerInterface, EntityRelationMutationHandlerInterface, EntityRevisionHandlerInterface
{
    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $attributes = (array) ($args['attributes'] ?? []);
        if (! array_key_exists('items_json', $args) && array_key_exists('items_json', $attributes)) {
            $args['items_json'] = $attributes['items_json'];
        }
        unset($attributes['items_json']);
        Validator::make($attributes + array_intersect_key($args, array_flip(['translations', 'market_ids', 'items_json'])), [
            'translations' => 'nullable|array', 'translations.*' => 'array',
            'translations.*.title' => 'nullable|string|max:255', 'translations.*.name' => 'nullable|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255', 'translations.*.description' => 'nullable|string|max:65535',
            'translations.*.meta_title' => 'nullable|string|max:255', 'translations.*.meta_description' => 'nullable|string|max:65535',
            'translations.*.schema_type' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean', 'market_ids' => 'nullable|array', 'market_ids.*' => 'integer|exists:markets,id',
        ])->validate();
        $translations = (array) ($args['translations'] ?? []);
        foreach ($translations as $code => $values) {
            if (! is_array($values)) {
                continue;
            }
            if (isset($values['name']) && ! array_key_exists('title', $values)) {
                $values['title'] = $values['name'];
            }
            unset($values['name']);
            if (isset($values['slug'])) {
                $values['slug'] = Str::slug((string) $values['slug']);
            }
            if ($creating) {
                $values['meta_title'] ??= $values['title'] ?? '';
                $values['meta_description'] ??= Str::limit(strip_tags((string) ($values['description'] ?? '')), 160);
                $values['schema_type'] ??= 'ImageGallery';
            }
            $translations[$code] = $values;
        }
        $locale = app(LocalizationServiceInterface::class)->defaultLocale();
        $primary = (array) ($translations[$locale] ?? []);
        if ($creating && trim((string) ($primary['title'] ?? '')) === '') {
            foreach ($translations as $values) {
                if (is_array($values) && trim((string) ($values['title'] ?? '')) !== '') {
                    $primary = $values;
                    break;
                }
            }
        }
        if ($creating && trim((string) ($primary['title'] ?? $attributes['title'] ?? '')) === '') {
            throw ValidationException::withMessages(['translations.title' => 'El título del álbum es obligatorio.']);
        }
        foreach (['title', 'description', 'slug'] as $field) {
            if (array_key_exists($field, $primary)) {
                $attributes[$field] = $primary[$field];
            }
        }
        if ($creating) {
            $attributes += ['is_active' => false];
        }
        if (array_key_exists('items_json', $args)) {
            $args['items'] = $this->validatedItems($args['items_json']);
        }

        return array_replace($args, ['attributes' => $attributes, 'translations' => $translations]);
    }

    public function syncRelations(Model $entity, array $args): void
    {
        assert($entity instanceof Gallery);
        if (array_key_exists('market_ids', $args)) {
            $entity->markets()->sync((array) $args['market_ids']);
        }
        if (! array_key_exists('items', $args)) {
            return;
        }
        $entity->items()->delete();
        foreach ((array) $args['items'] as $index => $item) {
            $entity->items()->create((array) $item + ['order' => $index + 1]);
        }
    }

    public function captureRevision(Model $entity): array
    {
        assert($entity instanceof Gallery);

        return ['items' => $entity->items()->get()->map(static fn (GalleryItem $item): array => $item->getAttributes())->all()];
    }

    public function validateRevision(array $revision): void
    {
        if (! isset($revision['items']) || ! is_array($revision['items']) || ! array_is_list($revision['items'])) {
            throw new \DomainException('Gallery revisions require canonical item records.');
        }
        foreach ($revision['items'] as $item) {
            if (! is_array($item) || $item === [] || array_is_list($item)) {
                throw new \DomainException('Gallery revisions require canonical item rows.');
            }
        }
    }

    public function hydrateRevision(Model $entity, array $snapshot): void
    {
        $items = [];
        foreach ((array) ($snapshot['items'] ?? []) as $record) {
            $item = (new GalleryItem)->newInstance([], exists: true);
            $item->setRawAttributes((array) $record, sync: true);
            $items[] = $item;
        }
        $entity->setRelation('items', new Collection($items));
    }

    public function restoreRevision(Model $entity, array $snapshot): void
    {
        assert($entity instanceof Gallery);
        $entity->items()->delete();
        $records = (array) ($snapshot['items'] ?? []);
        if ($records !== []) {
            GalleryItem::query()->insert($records);
        }
        $entity->unsetRelation('items');
    }

    /** @return list<array<string,mixed>> */
    private function validatedItems(mixed $json): array
    {
        $items = is_string($json) ? json_decode($json, true) : $json;
        if (! is_array($items) || ! array_is_list($items) || count($items) > 500) {
            throw ValidationException::withMessages(['items_json' => 'Las imágenes deben ser una lista JSON válida de hasta 500 elementos.']);
        }
        $result = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(['items_json' => 'Cada imagen debe ser un objeto.']);
            }
            $validation = Validator::make($item, ['image_url' => 'required|string|max:2048', 'title' => 'nullable|string|max:255', 'alt_text' => 'nullable|string|max:255', 'caption' => 'nullable|string|max:255']);
            if ($validation->fails()) {
                throw ValidationException::withMessages(['items_json' => 'Los datos de la imagen no son válidos.']);
            }
            $url = (string) $item['image_url'];
            $local = str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\');
            $remote = filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
            if (preg_match('/[\x00-\x20\x7f]/', $url) || (! $local && ! $remote)) {
                throw ValidationException::withMessages(['items_json' => 'La URL de la imagen debe ser una ruta local o HTTP(S).']);
            }
            $result[] = array_intersect_key($item, array_flip(['image_url', 'title', 'alt_text', 'caption']));
        }

        return $result;
    }
}
