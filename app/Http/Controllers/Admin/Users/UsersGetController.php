<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Users;

use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class UsersGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = User::with('roles');

        $users = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
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
