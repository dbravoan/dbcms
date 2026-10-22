<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Contracts\CategoryDomainServiceInterface;
use Modules\Blog\Http\Requests\CategoryMutationRequest;

final class CategoryPostController extends Controller
{
    public function __construct(private readonly CategoryDomainServiceInterface $service) {}

    public function __invoke(CategoryMutationRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user());

        return redirect()->route('admin.blog.categories.index')->with('success', 'Categoría guardada correctamente.');
    }
}
