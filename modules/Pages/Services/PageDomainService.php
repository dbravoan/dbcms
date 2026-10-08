<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\ExecutionContext;
use App\DTOs\Content\EntityMutationData;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Capabilities\EntityCrudService;
use App\Services\Content\EditorialDraftService;
use Illuminate\Support\Facades\Auth;
use Modules\Pages\Contracts\PageDomainServiceInterface;
use Modules\Pages\Models\Page;

class PageDomainService implements PageDomainServiceInterface
{
    /**
     * Resolves a published page by its localized slug for the given market and locale.
     */
    public function resolvePageBySlug(string $slug, ?Market $market = null, ?string $locale = null): ?Page
    {
        $locale = $locale ?? app()->getLocale();

        $page = Page::published()
            ->forMarket($market)
            ->whereHas('translations', function ($q) use ($slug, $locale): void {
                $q->where('slug', $slug)->where('locale', $locale);
            })
            ->with(['translations', 'sections.translations'])
            ->first();

        if ($page) {
            return $page;
        }

        // Fallback for homepage requests: return primary published page
        if ($slug === 'home' || $slug === 'inicio' || $slug === '') {
            return Page::published()
                ->forMarket($market)
                ->whereHas('translations', fn ($translation) => $translation->where('locale', $locale))
                ->with(['translations', 'sections.translations'])
                ->first();
        }

        return null;
    }

    public function __construct(private readonly ?EntityCrudService $crud = null) {}

    /** @param array<string,mixed> $data */
    public function createPage(array $data, ?User $actor = null): Page
    {
        $actor ??= Auth::user();
        $actor ??= app(CapabilityAuthorizer::class)->user(app(ExecutionContext::class)->actor ?? new ActorContext(null));
        abort_unless($actor instanceof User, 403);
        $payload = $this->entities()->create('page', EntityMutationData::fromLegacy($data)->arguments, $actor);
        $page = $this->entities()->resolveEntity('page', $payload['id']);
        assert($page instanceof Page);

        return $page->load(['translations', 'sections.translations', 'markets']);
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function updatePage(Page $page, array $data): array
    {
        $arguments = EntityMutationData::fromLegacy($data)->arguments;
        if ($page->is_published && ! empty($arguments['attributes']['is_published'])) {
            unset($arguments['attributes']['is_published'], $arguments['attributes']['published_at']);
        }

        return $this->entities()->update('page', $page->getKey(), $arguments);
    }

    public function deletePage(Page $page): void
    {
        $this->entities()->delete('page', $page->getKey());
    }

    /** @return array<string,mixed> */
    public function editData(Page $page): array
    {
        $page->load(['translations', 'sections.translations', 'markets']);
        $page = app(EditorialDraftService::class)->forEditor($page);
        assert($page instanceof Page);
        $data = [
            'page' => $page,
            'markets' => Market::with('languages')->where('is_active', true)->get(),
            'languages' => Language::where('is_active', true)->get(),
        ];

        return $data;
    }

    private function entities(): EntityCrudService
    {
        return $this->crud ?? app(EntityCrudService::class);
    }
}
