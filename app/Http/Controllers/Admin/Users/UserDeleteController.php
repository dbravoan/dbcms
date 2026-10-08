<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Users;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class UserDeleteController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(User $user): RedirectResponse
    {
        return $this->resources->delete('user', $user);
    }
}
