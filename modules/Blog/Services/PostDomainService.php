<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\ExecutionContext;
use App\DTOs\Content\EntityMutationData;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Capabilities\EntityCrudService;
use App\Services\Content\EditorialDraftService;
use App\Services\Seo\ContentQualityAuditorService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

class PostDomainService implements PostDomainServiceInterface
{
    public function __construct(private readonly ?EntityCrudService $crud = null) {}

    /** @param array<string,mixed> $data */
    public function createPost(array $data, ?User $actor = null): Post
    {
        $actor ??= Auth::user();
        $actor ??= app(CapabilityAuthorizer::class)->user(app(ExecutionContext::class)->actor ?? new ActorContext(null));
        abort_unless($actor instanceof User, 403);
        $payload = $this->entities()->create('post', EntityMutationData::fromLegacy($data)->arguments, $actor);
        $post = $this->entities()->resolveEntity('post', $payload['id']);
        assert($post instanceof Post);

        return $post->load(['translations', 'sections.translations', 'markets', 'category', 'author']);
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function updatePost(Post $post, array $data): array
    {
        $arguments = EntityMutationData::fromLegacy($data)->arguments;
        if ($post->is_published && ! empty($arguments['attributes']['is_published'])) {
            unset($arguments['attributes']['is_published'], $arguments['attributes']['published_at']);
        }

        return $this->entities()->update('post', $post->getKey(), $arguments);
    }

    public function deletePost(Post $post): void
    {
        $this->entities()->delete('post', $post->getKey());
    }

    public function syncSections(Post $post, string $sectionsJson): void
    {
        $this->entities()->update('post', $post->getKey(), ['sections_json' => $sectionsJson]);
    }

    /** @return array<string,mixed> */
    public function editData(Post $post): array
    {
        $post->load(['translations', 'sections.translations', 'markets']);
        $post = app(EditorialDraftService::class)->forEditor($post);
        assert($post instanceof Post);
        $data = [
            'post' => $post,
            'markets' => Market::with('languages')->where('is_active', true)->get(),
            'languages' => Language::where('is_active', true)->get(),
        ];
        $translation = $post->translations->firstWhere('locale', app(LocalizationServiceInterface::class)->defaultLocale()) ?? $post->translations->first();
        $data['categories'] = Category::with('translations')->get();
        $data['qualityReport'] = app(ContentQualityAuditorService::class)->auditPost((string) ($translation->title ?? ''), (string) ($translation->content ?? ''));

        return $data;
    }

    private function entities(): EntityCrudService
    {
        return $this->crud ?? app(EntityCrudService::class);
    }

    /**
     * Queries published posts scoped by market and locale.
     *
     * @return LengthAwarePaginator<int, Post>
     */
    public function getFeed(?Market $market = null, ?string $locale = null, int $perPage = 12, ?int $page = null): LengthAwarePaginator
    {
        $locale = $locale ?? app()->getLocale();

        return Post::published()
            ->forMarket($market)
            ->whereHas('translations', function ($q) use ($locale): void {
                $q->where('locale', $locale);
            })
            ->with(['translations' => function ($q) use ($locale): void {
                $q->where('locale', $locale);
            }, 'author', 'category.translations'])
            ->latest('published_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
