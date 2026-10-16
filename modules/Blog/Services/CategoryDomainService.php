<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Core\Traits\HandlesDatatableQuery;
use App\DTOs\Content\EntityMutationData;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Capabilities\EntityMutationService;
use App\Services\Capabilities\EntityReadService;
use App\Services\Content\EditorialDraftService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Blog\Contracts\CategoryDomainServiceInterface;
use Modules\Blog\Models\Category;

final class CategoryDomainService implements CategoryDomainServiceInterface
{
    use HandlesDatatableQuery;

    public function __construct(private readonly EntityMutationService $entities, private readonly EntityReadService $reader, private readonly EditorialDraftService $drafts) {}

    public function create(array $input, ?User $actor): Category
    {
        $payload = $this->entities->create('blog_category', EntityMutationData::fromForm($input)->arguments, $actor);
        $category = $this->reader->resolveEntity('blog_category', $payload['id']);
        assert($category instanceof Category);

        return $category;
    }

    public function update(Category $category, array $input): array
    {
        $args = EntityMutationData::fromForm($input)->arguments;
        if ($category->is_active && ! empty($args['attributes']['is_active'])) {
            unset($args['attributes']['is_active']);
        }

        return $this->entities->update('blog_category', $category->getKey(), $args);
    }

    public function delete(Category $category): void
    {
        $this->entities->delete('blog_category', $category->getKey());
    }

    public function indexData(Request $request): array
    {
        $query = Category::with(['translations', 'parent.translations', 'markets'])->withCount('posts');
        $categories = $this->applyDatatable($query, $request, ['translations.name', 'translations.slug'], ['is_active' => 'is_active', 'parent_id' => 'parent_id'], 'created_at', 'desc');
        assert($categories instanceof LengthAwarePaginator);
        $categories->setCollection($categories->getCollection()->map(function (mixed $category): Category {
            assert($category instanceof Category);
            $draft = $this->drafts->forEditor($category);
            assert($draft instanceof Category);
            $draft->setAttribute('posts_count', $category->getAttribute('posts_count'));
            if (($draft->getAttributes()['pending_revision_id'] ?? null) !== null) {
                $draft->setAttribute('review_url', route('admin.audit.review', ['type' => 'blog_categories', 'id' => $category->id]));
            }
            $draft->loadMissing('parent.translations');

            return $draft;
        }));

        return [
            'categories' => $categories,
            'allCategories' => Category::with(['translations', 'markets'])->get(),
            'languages' => Language::query()->where('is_active', true)->orderBy('name')->get(),
            'markets' => Market::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
