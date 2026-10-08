<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\DTOs\Content\EntityMutationData;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Capabilities\EntityCrudService;
use App\Services\Content\EditorialDraftService;
use Illuminate\Database\Eloquent\Model;
use Modules\Shop\Contracts\ShopEditorialServiceInterface;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

/** HTTP adapter over the same transactional entity writer used by native capabilities. */
final class ShopEditorialService implements ShopEditorialServiceInterface
{
    public function __construct(private readonly EntityCrudService $entities) {}

    /** @param array<string,mixed> $input */
    public function create(string $type, array $input, ?User $actor): Model
    {
        $payload = $this->entities->create($type, EntityMutationData::fromLegacy($input)->arguments, $actor);

        return $this->entities->resolveEntity($type, $payload['id']);
    }

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function update(string $type, Model $entity, array $input): array
    {
        $args = EntityMutationData::fromLegacy($input)->arguments;
        if ($entity->getAttribute('is_active') && ! empty($args['attributes']['is_active'])) {
            unset($args['attributes']['is_active']);
        }

        return $this->entities->update($type, $entity->getKey(), $args);
    }

    public function delete(string $type, Model $entity): void
    {
        $this->entities->delete($type, $entity->getKey());
    }

    /** @return array<string,mixed> */
    public function editProduct(Product $product): array
    {
        $product->load(['translations', 'category.translations', 'markets', 'sections.translations']);

        return [
            'product' => app(EditorialDraftService::class)->forEditor($product),
            'categories' => ProductCategory::with('translations')->get(),
            'markets' => Market::with('languages')->where('is_active', true)->get(),
            'languages' => Language::where('is_active', true)->get(),
            'isShop' => app(ShopDomainService::class)->isShopEnabled(),
        ];
    }
}
