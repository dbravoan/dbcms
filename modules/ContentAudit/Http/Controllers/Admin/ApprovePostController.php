<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\ContentAudit\Http\Adapters\EditorialReviewTransport;

final class ApprovePostController extends Controller
{
    public function __construct(private readonly EditorialReviewTransport $transport) {}

    public function __invoke(Request $request, string $type, int $id): RedirectResponse
    {
        return $this->transport->approve($request, $type, $id);
    }
}
