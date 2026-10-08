<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Markets;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Http\RedirectResponse;

/**
 * Enables/disables a market. The default market is protected: it anchors the
 * canonical routing of the whole site.
 */
final class MarketTogglePostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(Market $market): RedirectResponse
    {
        return $this->resources->toggle('market', $market);
    }
}
