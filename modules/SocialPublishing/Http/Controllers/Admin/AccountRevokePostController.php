<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Services\SocialOAuthService;

final class AccountRevokePostController extends Controller
{
    public function __invoke(Request $request, string $account, SocialOAuthService $oauth, ActorContextFactory $actors): RedirectResponse
    {
        $oauth->revoke($account, $actors->fromRequest($request));

        return back()->with('success', 'Conexión local revocada y credenciales eliminadas.');
    }
}
