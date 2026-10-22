<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Markets;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResourceMutationRequest;
use App\Models\Market;
use Illuminate\Http\RedirectResponse;

final class MarketPutController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request, Market $market): RedirectResponse
    {
        return $this->resources->update('market', $market, $request);
    }
}
