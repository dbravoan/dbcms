<?php

declare(strict_types=1);

namespace Modules\Pages\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Pages\Contracts\PageDomainServiceInterface;
use Modules\Pages\Models\Page;

final class PageEditGetController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(Page $page): View
    {
        return view('admin.pages.edit', $this->service->editData($page));
    }
}
