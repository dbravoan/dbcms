<?php

declare(strict_types=1);

namespace Modules\Shop\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Shop\Models\Product;

interface ShopEditorialServiceInterface
{
    /** @param array<string,mixed> $input */
    public function create(string $type, array $input, ?User $actor): Model;

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function update(string $type, Model $entity, array $input): array;

    public function delete(string $type, Model $entity): void;

    /** @return array<string,mixed> */
    public function editProduct(Product $product): array;
}
