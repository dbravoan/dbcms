<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Blog\Contracts\CategoryDomainServiceInterface;

final class CategoriesGetController extends Controller
{
    public function __construct(private readonly CategoryDomainServiceInterface $service) {}

    public function __invoke(Request $request): View
    {
        return view('admin.blog.categories.index', $this->service->indexData(DatatableQueryData::fromInput($request->query())));
    }
}
