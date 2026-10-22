<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Menus;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;

final class MenuDeleteController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(Menu $menu): RedirectResponse
    {
        return $this->resources->delete('menu', $menu);
    }
}
