<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Menus;

use App\Contracts\Services\MenuEditorServiceInterface;
use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class MenusGetController extends Controller
{
    public function __construct(private readonly MenuEditorServiceInterface $editor) {}

    public function __invoke(Request $request): View
    {
        return view('admin.menus.index', $this->editor->index(DatatableQueryData::fromInput($request->all()))->viewData);
    }
}
