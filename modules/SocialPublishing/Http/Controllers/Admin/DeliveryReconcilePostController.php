<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Services\DeliveryWorker;
use Modules\SocialPublishing\Services\SocialAuthorization;

final class DeliveryReconcilePostController extends Controller
{
    public function __invoke(Request $request, string $delivery, DeliveryWorker $worker, SocialAuthorization $auth, ActorContextFactory $actors): RedirectResponse
    {
        $auth->authorize($actors->fromRequest($request));
        $worker->reconcile($delivery);

        return back()->with('success', 'Comprobación terminada. El resultado sigue pendiente si no existe evidencia correlacionada.');
    }
}
