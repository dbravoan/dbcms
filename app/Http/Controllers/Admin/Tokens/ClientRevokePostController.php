<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Tokens;

use App\Http\Controllers\Controller;
use App\Services\Auth\TokenRevocationService;
use Illuminate\Http\RedirectResponse;

final class ClientRevokePostController extends Controller
{
    public function __construct(private readonly TokenRevocationService $tokens) {}

    public function __invoke(string $id): RedirectResponse
    {
        $this->tokens->forClient($id);

        return back()->with('success', 'Cliente OAuth revocado correctamente.');
    }
}
