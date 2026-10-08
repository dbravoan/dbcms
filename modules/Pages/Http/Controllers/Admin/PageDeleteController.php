<?php

declare(strict_types=1);

namespace Modules\Pages\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Pages\Contracts\PageDomainServiceInterface;
use Modules\Pages\Models\Page;

final class PageDeleteController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(Page $page): RedirectResponse
    {
        $this->service->deletePage($page);

        return redirect()->route('admin.pages.index')->with('success', 'Página eliminada correctamente.');
    }
}
