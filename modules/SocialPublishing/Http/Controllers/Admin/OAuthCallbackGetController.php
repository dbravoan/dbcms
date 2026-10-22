<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Services\SocialOAuthService;

final class OAuthCallbackGetController extends Controller
{
    public function __invoke(Request $request, string $provider, SocialOAuthService $oauth, ActorContextFactory $actors): View
    {
        $data = $request->validate(['state' => 'required|string|max:100', 'code' => 'required|string|max:2000']);
        $state = $oauth->callback($provider, (string) $data['state'], (string) $data['code'], $actors->fromRequest($request), $request->session()->getId());

        return view('social-publishing::admin.select-account', ['stateId' => $state->id, 'provider' => $provider, 'accounts' => array_map(static fn (array $a): array => ['id' => $a['id'], 'label' => $a['label']], $state->discovery['accounts'])]);
    }
}
