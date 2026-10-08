<?php

declare(strict_types=1);

namespace App\Core\Sections;

use App\Contracts\Sectionable;
use App\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for persisting the block manager payload into the
 * relational section tables. Replaces the logic previously duplicated across
 * the Pages, Blog and Shop controllers/services.
 *
 * Accepts either a locale-keyed payload (`{es: [...], en: [...]}`) or a flat
 * list of blocks, matching the block manager transport format.
 */
class SectionSyncService
{
    /** Promote the current, reviewed section version without recreating its rows. */
    public function publish(Model $entity): void
    {
        if (! $entity instanceof Sectionable) {
            return;
        }
        $attributes = $entity->getAttributes();
        if (! (bool) ($attributes['is_published'] ?? $attributes['is_active'] ?? false)) {
            return;
        }
        $sectionModel = $entity->sectionModelClass();
        $sectionModel::query()->where($entity->sectionForeignKey(), $entity->getKey())
            ->where('status', 'draft')->update(['status' => 'published', 'published_at' => now()]);
        $entity->unsetRelation('sections');
        $entity->unsetRelation('publishedSections');
    }

    public function sync(Model $entity, string $sectionsJson, string $status = 'published'): void
    {
        if (! $entity instanceof Sectionable) {
            return;
        }

        $decoded = json_decode($sectionsJson, true);
        if (! is_array($decoded)) {
            return;
        }

        $status = in_array($status, ['draft', 'published'], true) ? $status : 'published';
        $languages = $this->activeLocales();

        DB::transaction(function () use ($entity, $decoded, $languages, $status): void {
            $sectionModel = $entity->sectionModelClass();
            $translationModel = $entity->sectionTranslationModelClass();
            $entityFk = $entity->sectionForeignKey();
            $translationFk = Str::snake(class_basename($sectionModel)).'_id';

            // Hints never grant access to another entity's section or choose a new PK.
            $existing = $sectionModel::query()->where($entityFk, $entity->getKey())->lockForUpdate()->get()->keyBy('id');
            $retained = [];
            $marketAware = in_array('market_id', (new $translationModel)->getFillable(), true);

            foreach ($this->plan($decoded, $languages) as $order => $item) {
                // Canonical shape on write: the section content moves into its
                // first column and the block grid size lives in `grid_columns`.
                $master = SectionLayout::normalize($item['master']);

                $hint = $this->identityHint($master['id'] ?? null);
                unset($master['id']);
                $section = $hint === null ? null : $existing->get($hint);
                if ($section !== null && in_array((int) $section->getKey(), $retained, true)) {
                    throw ValidationException::withMessages(['sections_json' => 'A section identity cannot appear more than once.']);
                }
                $section ??= new $sectionModel;
                $section->fill([
                    $entityFk => $entity->getKey(),
                    'type' => $master['type'] ?? 'text',
                    'order' => $order + 1,
                    'is_active' => true,
                    'styling_json' => $master,
                    'status' => $status,
                    'published_at' => $status === 'published' ? now() : null,
                ])->save();
                $retained[] = (int) $section->getKey();

                $removedTranslations = $translationModel::query()->where($translationFk, $section->getKey())
                    ->whereIn('locale', $languages)->whereNotIn('locale', array_keys($item['contents']));
                if ($marketAware) {
                    $removedTranslations->whereNull('market_id');
                }
                $removedTranslations->delete();

                foreach ($item['contents'] as $locale => $content) {
                    if (is_array($content)) {
                        $content = SectionLayout::normalize($content);
                        unset($content['id']);
                    }

                    $identity = [$translationFk => $section->getKey(), 'locale' => $locale];
                    if ($marketAware) {
                        $identity['market_id'] = null;
                    }
                    $translationModel::query()->updateOrCreate($identity, ['content_json' => $content]);
                }
            }
            $sectionModel::query()->where($entityFk, $entity->getKey())->whereNotIn('id', $retained)->delete();
            $entity->unsetRelation('sections');
            $entity->unsetRelation('publishedSections');
        });
    }

    /** Only exact persisted numeric IDs and their legacy editor prefix are hints. */
    private function identityHint(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (! is_string($value) || ! preg_match('/^(?:sec_)?([1-9][0-9]*)$/D', $value, $matches)) {
            return null;
        }
        $id = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($id) ? $id : null;
    }

    /**
     * @return list<string>
     */
    private function activeLocales(): array
    {
        $locales = Language::query()->where('is_active', true)->pluck('code')->all();

        if ($locales === []) {
            return ['es'];
        }

        return array_values(array_map('strval', $locales));
    }

    /**
     * Normalize the payload into an ordered list of {master, contents} items.
     *
     * @param  array<mixed>  $decoded
     * @param  list<string>  $languages
     * @return list<array{master: array<string, mixed>, contents: array<string, array<string, mixed>>}>
     */
    private function plan(array $decoded, array $languages): array
    {
        $isLocaleKeyed = false;
        foreach (array_keys($decoded) as $key) {
            if (is_string($key) && ! is_numeric($key)) {
                $isLocaleKeyed = true;
                break;
            }
        }

        $plan = [];

        if ($isLocaleKeyed) {
            $max = 0;
            foreach ($decoded as $list) {
                if (is_array($list)) {
                    $max = max($max, count($list));
                }
            }

            for ($index = 0; $index < $max; $index++) {
                $master = $this->firstBlockAt($decoded, $index);
                if ($master === null) {
                    continue;
                }

                $contents = [];
                foreach ($languages as $locale) {
                    $content = $this->blockAt($decoded[$locale] ?? null, $index);
                    if ($content !== null) {
                        $contents[$locale] = $content;
                    }
                }

                $plan[] = ['master' => $master, 'contents' => $contents];
            }

            return $plan;
        }

        foreach ($decoded as $block) {
            if (! is_array($block)) {
                continue;
            }

            /** @var array<string, mixed> $block */
            $plan[] = [
                'master' => $block,
                'contents' => array_fill_keys($languages, $block),
            ];
        }

        return $plan;
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<string, mixed>|null
     */
    private function firstBlockAt(array $decoded, int $index): ?array
    {
        $preferred = $this->blockAt($decoded['es'] ?? null, $index);
        if ($preferred !== null) {
            return $preferred;
        }

        foreach ($decoded as $list) {
            $block = $this->blockAt($list, $index);
            if ($block !== null) {
                return $block;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blockAt(mixed $list, int $index): ?array
    {
        if (! is_array($list) || ! isset($list[$index]) || ! is_array($list[$index])) {
            return null;
        }

        /** @var array<string, mixed> $block */
        $block = $list[$index];

        return $block;
    }
}
