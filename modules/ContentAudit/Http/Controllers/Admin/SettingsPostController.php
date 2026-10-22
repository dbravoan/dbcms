<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\ContentAudit\Contracts\Services\AuditWorkbenchServiceInterface;
use Modules\ContentAudit\Http\Requests\AuditSettingsRequest;

final class SettingsPostController extends Controller
{
    public function __construct(private readonly AuditWorkbenchServiceInterface $workbench) {}

    public function __invoke(AuditSettingsRequest $request): RedirectResponse
    {
        $this->workbench->updateSettings($request->validated());

        return redirect()->route('admin.audit.index', ['tab' => 'settings'])->with('success', 'Políticas de auditoría guardadas.');
    }
}
