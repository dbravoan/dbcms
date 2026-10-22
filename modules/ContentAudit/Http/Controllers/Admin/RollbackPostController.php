<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\ContentAudit\Http\Adapters\EditorialReviewTransport;

final class RollbackPostController extends Controller
{
    public function __construct(private readonly EditorialReviewTransport $transport) {}

    public function __invoke(Request $request, int $revisionId): RedirectResponse
    {
        return $this->transport->rollback($request, $revisionId);
    }
}
