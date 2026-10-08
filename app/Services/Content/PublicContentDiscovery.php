<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/** Shared public visibility and URL contract for sitemap, feeds and semantic discovery. */
final class PublicContentDiscovery implements PublicContentDiscoveryInterface
{
    public function __construct(private readonly EntitySchemaRegistryInterface $entities, private readonly ModuleManager $modules, private readonly LocalizationServiceInterface $localization) {}

    /**
     * @param  list<string>  $relations
     * @return Builder<Model>
     */
    public function query(string $type, Market $market, string $locale, array $relations = []): Builder
    {
        $schema = $this->entities->get($type) ?? throw new InvalidArgumentException('Unknown public content type.');
        /** @var class-string<Model> $class */
        $class = $schema->model;
        $model = new $class;
        $publicRelations = method_exists($model, 'publishedSections') ? ['publishedSections.translations'] : [];
        $query = $model->newQuery()->with(array_values(array_unique(['translations', 'markets', ...$publicRelations, ...$relations])));
        $owner = $schema->ownerModule ?? $this->modules->ownerOfClass($class);
        if (($owner !== null && ! $this->modules->isModuleEnabled($owner)) || ! $market->is_active || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return $query->whereRaw('1 = 0');
        }
        if (method_exists($model, 'scopePublished')) {
            $query->scopes('published');
        } elseif (in_array('is_published', $model->getFillable(), true)) {
            $query->where('is_published', true);
        } elseif (in_array('is_active', $model->getFillable(), true)) {
            $query->where('is_active', true);
        } else {
            return $query->whereRaw('1 = 0');
        }
        $query->whereHas('translations', fn (Builder $translation) => $translation->where('locale', $locale)->whereNotNull('slug')->where('slug', '!=', ''));
        if (method_exists($model, 'scopeForMarket')) {
            // Respect module-owned availability, including Product market pivots.
            $query->scopes(['forMarket' => [$market]]);
        } else {
            $query->where(fn (Builder $visible) => $visible->whereHas('markets', fn (Builder $markets) => $markets->whereKey($market->getKey()))->orDoesntHave('markets'));
        }

        return $query;
    }

    /** @return Collection<int, Market> */
    public function markets(): Collection
    {
        return Market::query()->where('is_active', true)->with(['languages' => fn ($languages) => $languages->where('is_active', true)])->get();
    }

    public function url(string $type, Market $market, string $locale, string $slug): string
    {
        $schema = $this->entities->get($type) ?? throw new InvalidArgumentException('Unknown public content type.');
        $prefix = $schema->urlPrefix ?? $schema->searchPrefix ?? '';
        $prefix = $prefix !== '' ? $this->localization->getModulePrefix($prefix, $locale) : '';
        $path = $type === 'page' && $slug === 'home' ? '' : trim($prefix.'/'.$slug, '/');

        return $this->localization->url($path, $market, $locale);
    }
}
