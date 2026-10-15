<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Contracts\PublicationServiceInterface;

final class DeliveryCancelPostController extends Controller
{
    public function __invoke(Request $request, string $delivery, PublicationServiceInterface $service, ActorContextFactory $actors): RedirectResponse
    {
        $service->cancel($delivery, $actors->fromRequest($request));

        return redirect()->route('admin.social-publishing.index')->with('success', 'Cancelación registrada. Comprueba los resultados de envíos ya iniciados.');
    }
}
