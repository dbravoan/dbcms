<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Menus;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;

final class MenuItemDeleteController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(MenuItem $item): RedirectResponse
    {
        return $this->resources->delete('menu_item', $item);
    }
}
