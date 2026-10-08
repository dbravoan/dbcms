<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Users;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class UsersGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = User::with('roles');

        $users = $this->applyDatatable(
            $query,
            $request,
            ['name', 'email'],
            ['role' => 'roles.slug'],
            'created_at',
            'desc'
        );

        $roles = Role::all();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }
}
