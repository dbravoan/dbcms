<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Contracts\PublicationServiceInterface;

final class PublicationApprovePostController extends Controller
{
    public function __invoke(Request $request, string $publication, PublicationServiceInterface $service, ActorContextFactory $actors): RedirectResponse
    {
        $data = $request->validate(['payload_hash' => 'required|string|size:64', 'brand_checked' => 'accepted', 'rights_checked' => 'accepted', 'publish_at' => 'nullable|string|max:40']);
        $actor = $actors->fromRequest($request);
        $p = isset($data['publish_at']) ? $service->approveAndSchedule($publication, (string) $data['payload_hash'], (string) $data['publish_at'], $actor) : $service->approve($publication, (string) $data['payload_hash'], $actor);

        return redirect()->route('admin.social-publishing.preview', $p->id)->with('success', 'Revisión aprobada.');
    }
}
