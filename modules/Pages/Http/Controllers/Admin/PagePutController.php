<?php

declare(strict_types=1);

namespace Modules\Pages\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditorialEntityRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Pages\Contracts\PageDomainServiceInterface;
use Modules\Pages\Models\Page;

final class PagePutController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(EditorialEntityRequest $request, Page $page): RedirectResponse
    {
        $this->service->updatePage($page, $request->validated());

        return redirect()->route('admin.pages.index')->with('success', 'Página actualizada correctamente.');
    }
}
