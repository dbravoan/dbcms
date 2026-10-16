<?php

declare(strict_types=1);

namespace App\Content\Pages\Http\Controllers\Admin;

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Models\Page;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class PageDeleteController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(Page $page): RedirectResponse
    {
        $this->service->deletePage($page);

        return redirect()->route('admin.pages.index')->with('success', 'Página eliminada correctamente.');
    }
}
