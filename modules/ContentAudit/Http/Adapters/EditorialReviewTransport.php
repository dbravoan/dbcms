<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Http\Adapters;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Models\User;
use App\Services\Capabilities\ContentSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\ContentAudit\Contracts\Services\DiffEngineServiceInterface;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Http\Presenters\EditorialReviewPresenter;
use Modules\ContentAudit\Models\ContentAuditRevision;
use Modules\ContentAudit\Services\AuditEntityPresentation;
use Modules\ContentAudit\Services\AuditRevisionService;
use Modules\ContentAudit\Services\EditorialApprovalBinding;

final class EditorialReviewTransport
{
    public function __construct(private readonly AuditEntityPresentation $presentation, private readonly AuditRevisionService $revisions, private readonly ContentSnapshotService $snapshots, private readonly DiffEngineServiceInterface $diffs, private readonly EditorialApprovalBinding $binding) {}

    public function review(Request $request, string $type, int $id): View|RedirectResponse
    {
        $entity = $this->presentation->find($type, $id);
        if ($entity === null) {
            return redirect()->route('admin.audit.index')->withErrors(['error' => "No se encontró el contenido solicitado [{$type} #{$id}]."]);
        }
        $auditor = $request->user();
        abort_unless($auditor instanceof User, 403);
        $review = $this->binding->review($entity, $auditor);
        $liveSnapshot = $this->snapshots->capture($entity);
        $entity = $review['entity'];
        $audit = $this->revisions->auditFor($entity);
        $revisionQuery = ContentAuditRevision::query()
            ->where('auditable_type', $entity->getMorphClass())
            ->where('auditable_id', $entity->getKey())
            ->orderByDesc('version');
        $revisions = (clone $revisionQuery)->with('user')->get(ContentAuditRevision::LIST_COLUMNS);
        // Only the diff needs the previous snapshot; fetch that one row in full.
        $previous = (clone $revisionQuery)->skip(1)->first() ?? (clone $revisionQuery)->first();
        $diffResult = $this->diffs->computeDiff($audit?->pending_revision_id !== null ? $liveSnapshot : ($previous ? (array) $previous->snapshot : []), $review['snapshot']);
        $description = $this->presentation->describe($entity);
        $rollbackTokens = [];
        foreach ($revisions as $revision) {
            $rollbackTokens[$revision->getKey()] = $this->binding->sealRollback($review['token'], $revision->version);
        }

        $editorUrl = null;
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model && $schema->editView !== null && Route::has($schema->editView)) {
                $editorUrl = route($schema->editView, $entity->getKey());
                break;
            }
        }

        return view('content_audit::admin.review', ['editorUrl' => $editorUrl, 'entity' => $entity, 'audit' => $audit, 'revisions' => $revisions, 'diffResult' => $diffResult, 'type' => $type, 'id' => $id, 'reviewToken' => $review['token'], 'rollbackTokens' => $rollbackTokens, 'reviewSummary' => app(EditorialReviewPresenter::class)->summary($review['snapshot']), 'requireRejectionReason' => (bool) app(ModuleSettingsServiceInterface::class)->get('content_audit', 'require_rejection_reason', true), 'entityTitle' => $description['title'], 'entitySlug' => $description['slug'], 'entityIsPublished' => $description['is_published'], 'entityTranslations' => $description['translations']]);
    }

    public function approve(Request $request, string $type, int $id): RedirectResponse
    {
        $values = $request->validate(['review_token' => ['required', 'string'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $entity = $this->presentation->find($type, $id);
        if ($entity === null) {
            return redirect()->route('admin.audit.index')->withErrors(['error' => 'Contenido no encontrado.']);
        }
        $auditor = $request->user();
        abort_unless($auditor instanceof User, 403);
        $this->binding->approve($entity, $auditor, $values['review_token'], $values['notes'] ?? null);

        return redirect()->route('admin.audit.index')->with('success', "El contenido [{$type} #{$id}] ha sido aprobado.");
    }

    public function reject(Request $request, string $type, int $id): RedirectResponse
    {
        $required = (bool) app(ModuleSettingsServiceInterface::class)->get('content_audit', 'require_rejection_reason', true);
        $values = $request->validate(['review_token' => ['required', 'string'], 'rejection_reason' => [$required ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $entity = $this->presentation->find($type, $id);
        abort_unless($entity !== null, 404);
        $auditor = $request->user();
        abort_unless($auditor instanceof User, 403);
        $this->binding->reject($entity, $auditor, $values['review_token'], $values['rejection_reason'] ?? '', $values['notes'] ?? null);

        return redirect()->route('admin.audit.index')->with('success', 'El contenido ha sido devuelto al autor.');
    }

    public function rollback(Request $request, int $revisionId): RedirectResponse
    {
        $values = $request->validate(['review_token' => ['required', 'string']]);
        $revision = ContentAuditRevision::findOrFail($revisionId);
        $auditor = $request->user();
        abort_unless($auditor instanceof User, 403);
        app(EditorialVisibilityInterface::class)->authorizeRevision($revision, $auditor);
        $entity = $this->presentation->find($revision->auditable_type, $revision->auditable_id);
        abort_unless($entity !== null, 404);
        $this->binding->rollback($entity, $auditor, $values['review_token'], $revision->version);

        return back()->with('success', "Restaurada la versión histórica #{$revision->version}.");
    }
}
