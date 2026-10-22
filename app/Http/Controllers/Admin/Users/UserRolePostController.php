<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Users;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResourceMutationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class UserRolePostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request, User $user): RedirectResponse
    {
        return $this->resources->update('user', $user, $request);
    }
}
