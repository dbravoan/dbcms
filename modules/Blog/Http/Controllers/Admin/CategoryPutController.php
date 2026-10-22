<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Contracts\CategoryDomainServiceInterface;
use Modules\Blog\Http\Requests\CategoryMutationRequest;
use Modules\Blog\Models\Category;

final class CategoryPutController extends Controller
{
    public function __construct(private readonly CategoryDomainServiceInterface $service) {}

    public function __invoke(CategoryMutationRequest $request, Category $category): RedirectResponse
    {
        $this->service->update($category, $request->validated());

        return redirect()->route('admin.blog.categories.index')->with('success', 'Categoría guardada correctamente.');
    }
}
