<?php

declare(strict_types=1);

namespace Modules\Maintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class MaintenanceIndexGetController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('admin.modules.settings', ['identifier' => 'maintenance']);
    }
}
