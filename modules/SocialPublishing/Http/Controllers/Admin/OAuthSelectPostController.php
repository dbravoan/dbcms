<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Services\SocialOAuthService;

final class OAuthSelectPostController extends Controller
{
    public function __invoke(Request $request, SocialOAuthService $oauth, ActorContextFactory $actors): RedirectResponse
    {
        $data = $request->validate(['state_id' => 'required|uuid', 'remote_id' => 'required|string|max:200']);
        $oauth->select((string) $data['state_id'], (string) $data['remote_id'], $actors->fromRequest($request), $request->session()->getId());

        return redirect()->route('admin.social-publishing.index')->with('success', 'Cuenta autorizada y seleccionada.');
    }
}
