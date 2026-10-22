<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ContentAudit\Contracts\Services\AuditWorkbenchServiceInterface;

final class DiffGetController extends Controller
{
    public function __construct(private readonly AuditWorkbenchServiceInterface $workbench) {}

    public function __invoke(Request $request, int $revisionId): JsonResponse|View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $diff = $this->workbench->diff($user, $revisionId, $request->query('compare_with'));

        return $request->wantsJson() ? response()->json($diff->jsonData()) : view('content_audit::admin.partials.diff-viewer', $diff->viewData());
    }
}
