<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Users;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResourceMutationRequest;
use Illuminate\Http\RedirectResponse;

final class UserPostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request): RedirectResponse
    {
        return $this->resources->create('user', $request);
    }
}
