<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dashboard;

use App\Contracts\Services\AdminDashboardServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DashboardGetController extends Controller
{
    public function __construct(private readonly AdminDashboardServiceInterface $dashboard) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('admin.dashboard', $this->dashboard->data($user));
    }
}
