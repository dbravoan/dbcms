<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class ShopSettingsGetController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('admin.modules.settings', ['identifier' => 'shop']);
    }
}
