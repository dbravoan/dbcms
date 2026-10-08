<?php

declare(strict_types=1);

namespace Modules\Blog\Contracts;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Blog\Models\Category;

interface CategoryDomainServiceInterface
{
    /** @param array<string,mixed> $input */
    public function create(array $input, ?User $actor): Category;

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function update(Category $category, array $input): array;

    public function delete(Category $category): void;

    /** @return array<string,mixed> */
    public function indexData(Request $request): array;
}
