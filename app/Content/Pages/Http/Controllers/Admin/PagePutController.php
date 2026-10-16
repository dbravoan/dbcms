<?php

declare(strict_types=1);

namespace App\Content\Pages\Http\Controllers\Admin;

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Models\Page;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditorialEntityRequest;
use Illuminate\Http\RedirectResponse;

final class PagePutController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(EditorialEntityRequest $request, Page $page): RedirectResponse
    {
        $this->service->updatePage($page, $request->validated());

        return redirect()->route('admin.pages.index')->with('success', 'Página actualizada correctamente.');
    }
}
