<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Services\SocialOAuthService;

final class AccountManualPostController extends Controller
{
    public function __invoke(Request $request, SocialOAuthService $oauth, ActorContextFactory $actors): RedirectResponse
    {
        $data = $request->validate(['provider' => 'required|in:facebook,instagram,linkedin,youtube,tiktok', 'label' => 'required|string|max:150']);
        $oauth->manual((string) $data['provider'], (string) $data['label'], $actors->fromRequest($request));

        return back()->with('success', 'Destino preparado para exportación manual.');
    }
}
