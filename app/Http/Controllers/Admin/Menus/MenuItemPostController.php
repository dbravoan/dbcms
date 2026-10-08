<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Menus;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResourceMutationRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;

final class MenuItemPostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request, Menu $menu): RedirectResponse
    {
        return $this->resources->create('menu_item', $request);
    }
}
