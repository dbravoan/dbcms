<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Contracts\Services\UpdateCatalogServiceInterface;
use App\Contracts\Services\UpdateHistoryServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class UpdatesGetController
{
    public function __invoke(Request $request, UpdateCatalogServiceInterface $catalog, UpdateHistoryServiceInterface $history): View
    {
        return view('admin.updates.index', ['catalog' => $catalog->status(), 'recentRuns' => $history->recent((int) $request->user()?->getAuthIdentifier())]);
    }
}
