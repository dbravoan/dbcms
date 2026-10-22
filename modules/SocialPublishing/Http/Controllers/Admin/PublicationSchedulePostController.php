<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Contracts\PublicationServiceInterface;

final class PublicationSchedulePostController extends Controller
{
    public function __invoke(Request $request, string $publication, PublicationServiceInterface $service, ActorContextFactory $actors): RedirectResponse
    {
        $data = $request->validate(['publish_at' => 'required|string|max:40']);
        $service->schedule($publication, (string) $data['publish_at'], $actors->fromRequest($request));

        return redirect()->route('admin.social-publishing.preview', $publication)->with('success', 'Programación guardada.');
    }
}
