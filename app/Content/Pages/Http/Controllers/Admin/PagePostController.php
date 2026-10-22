<?php

declare(strict_types=1);

namespace App\Content\Pages\Http\Controllers\Admin;

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditorialEntityRequest;
use Illuminate\Http\RedirectResponse;

final class PagePostController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(EditorialEntityRequest $request): RedirectResponse
    {
        $this->service->createPage($request->validated(), $request->user());

        return redirect()->route('admin.pages.index')->with('success', 'Página creada correctamente.');
    }
}
