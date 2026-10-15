<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Modules\SocialPublishing\Http\Requests\DraftRequest;
use Modules\SocialPublishing\Services\PublicationInputService;

final class PublicationPostController extends Controller
{
    public function __invoke(DraftRequest $request, PublicationInputService $service, ActorContextFactory $actors): RedirectResponse
    {
        $p = $service->create($request->payload(), array_values((array) $request->file('files', [])), $actors->fromRequest($request));

        return redirect()->route('admin.social-publishing.preview', $p->id)->with('success', 'Borrador guardado. Revisa esta versión exacta.');
    }
}
