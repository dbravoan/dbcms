<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\ContentAudit\Contracts\Services\AuditWorkbenchServiceInterface;

final class IndexGetController extends Controller
{
    public function __construct(private readonly AuditWorkbenchServiceInterface $workbench) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('content_audit::admin.index', $this->workbench->index($user, $request->query()));
    }
}
