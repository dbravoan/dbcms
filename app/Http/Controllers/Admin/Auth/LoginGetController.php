<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class LoginGetController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }

        $adminPath = config('cms.admin_path', 'admin');

        return view('admin.auth.login', [
            'adminPath' => $adminPath,
        ]);
    }
}
