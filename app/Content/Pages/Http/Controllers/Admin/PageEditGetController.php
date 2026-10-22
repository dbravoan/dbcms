<?php

declare(strict_types=1);

namespace App\Content\Pages\Http\Controllers\Admin;

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Models\Page;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class PageEditGetController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(Page $page): View
    {
        return view('admin.pages.edit', $this->service->editData($page));
    }
}
