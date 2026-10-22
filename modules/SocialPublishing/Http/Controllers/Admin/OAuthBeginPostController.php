<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Services\SocialOAuthService;

final class OAuthBeginPostController extends Controller
{
    public function __invoke(Request $request, string $provider, SocialOAuthService $oauth, ActorContextFactory $actors): RedirectResponse
    {
        return redirect()->away($oauth->begin($provider, $actors->fromRequest($request), $request->session()->getId()));
    }
}
