<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\ContentAudit\Contracts\Services\AuditWorkbenchServiceInterface;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\ContentAudit\Contracts\Services\DiffEngineServiceInterface;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\DTOs\AuditDiffData;
use Modules\ContentAudit\Models\ContentAudit;
use Modules\ContentAudit\Models\ContentAuditRevision;

final class AuditWorkbenchService implements AuditWorkbenchServiceInterface
{
    public function __construct(private readonly ModuleSettingsServiceInterface $settingsService, private readonly ContentAuditServiceInterface $auditService, private readonly EditorialVisibilityInterface $visibility, private readonly DiffEngineServiceInterface $diffEngine) {}

    public function index(User $user, array $filters): array
    {
        $canManageSettings = $user->isAdmin() || $user->hasPermission('manage:modules');
        $tab = (string) ($filters['tab'] ?? 'queue');
        if (! in_array($tab, ['queue', 'changelog', 'settings'], true) || ($tab === 'settings' && ! $canManageSettings)) {
            $tab = 'queue';
        }
        $statusFilter = (string) ($filters['status'] ?? 'pending');
        $entityFilter = (string) ($filters['entity'] ?? 'all');

        $auditsQuery = ContentAudit::with(['auditable.translations', 'submitter', 'reviewer'])
            ->orderByDesc('updated_at');
        $this->visibility->constrain($auditsQuery, $user);

        if ($statusFilter === 'pending') {
            $auditsQuery->where('status', 'pending_review');
        } elseif ($statusFilter !== 'all') {
            $auditsQuery->where('status', $statusFilter);
        }

        if ($entityFilter !== 'all') {
            $auditsQuery->where('auditable_type', 'like', "%{$entityFilter}%");
        }

        $audits = $auditsQuery->paginate(15, ['*'], 'audits_page');

        // Resolve strict-mode safe titles once, so the view never probes missing attributes.
        $audits->through(function (ContentAudit $audit): ContentAudit {
            $entity = $audit->auditable;

            $audit->setAttribute(
                'entity_title',
                $entity instanceof Model ? $this->auditService->describeEntity($entity)['title'] : ''
            );

            return $audit;
        });

        $revisionsQuery = ContentAuditRevision::with(['auditable', 'user'])
            ->orderByDesc('created_at');
        $this->visibility->constrain($revisionsQuery, $user);

        if (isset($filters['rev_action']) && $filters['rev_action'] !== '') {
            $revisionsQuery->where('action', $filters['rev_action']);
        }

        if (isset($filters['rev_user']) && $filters['rev_user'] !== '') {
            $revisionsQuery->where('user_id', $filters['rev_user']);
        }

        $revisions = $revisionsQuery->paginate(20, ['*'], 'revisions_page');

        $settings = $this->settingsService->getAll('content_audit');
        $schema = $this->settingsService->getSchema('content_audit');
        $users = $canManageSettings ? User::orderBy('name')->get() : collect();
        $pendingQuery = ContentAudit::where('status', 'pending_review');
        $this->visibility->constrain($pendingQuery, $user);
        $pendingCount = $pendingQuery->count();

        return [
            'audits' => $audits,
            'revisions' => $revisions,
            'settings' => $settings,
            'schema' => $schema,
            'users' => $users,
            'pendingCount' => $pendingCount,
            'currentTab' => $tab,
            'canManageSettings' => $canManageSettings,
            'allRoles' => $canManageSettings ? Role::orderBy('name')->get() : collect(),
            'statusFilter' => $statusFilter,
            'entityFilter' => $entityFilter,
        ];
    }

    public function diff(User $user, int $revisionId, mixed $compareWithId): AuditDiffData
    {
        /** @var ContentAuditRevision $targetRevision */
        $targetRevision = ContentAuditRevision::findOrFail($revisionId);
        $this->visibility->authorizeRevision($targetRevision, $user);

        if ($compareWithId && is_numeric($compareWithId)) {
            /** @var ContentAuditRevision|null $compareRevision */
            $compareRevision = ContentAuditRevision::find((int) $compareWithId);
            abort_unless($compareRevision !== null && $compareRevision->auditable_type === $targetRevision->auditable_type
                && $compareRevision->auditable_id === $targetRevision->auditable_id, 403);
        } else {
            $compareQuery = ContentAuditRevision::where('auditable_type', $targetRevision->auditable_type)
                ->where('auditable_id', $targetRevision->auditable_id)
                ->where('version', '<', $targetRevision->version)
                ->orderByDesc('version');
            $this->visibility->constrain($compareQuery, $user);
            $compareRevision = $compareQuery->first();
        }

        if ($compareRevision !== null) {
            $this->visibility->authorizeRevision($compareRevision, $user);
        }

        $oldSnapshot = $compareRevision ? (array) $compareRevision->snapshot : [];
        $newSnapshot = (array) $targetRevision->snapshot;

        $diffResult = $this->diffEngine->computeDiff($oldSnapshot, $newSnapshot);

        return new AuditDiffData($targetRevision, $compareRevision, $diffResult);
    }

    public function sidebar(Model $entity, ?User $user): array
    {
        return ['audit' => $this->auditService->getAudit($entity), 'entityDescription' => $this->auditService->describeEntity($entity), 'canPublish' => $this->auditService->canPublishDirectly($user), 'entityType' => $this->auditService->resolveEntitySlug($entity)];
    }

    public function updateSettings(array $values): void
    {
        $payload = [];
        foreach (['is_enabled', 'auto_publish_on_approval', 'track_all_modifications', 'require_rejection_reason', 'enforce_editorial_quality', 'notify_in_app', 'notify_email'] as $key) {
            $payload[$key] = (bool) ($values[$key] ?? false);
        }
        foreach (['audited_roles' => ['author', 'marketing'], 'approver_roles' => ['admin', 'editor'], 'audited_entities' => ['posts', 'pages', 'products', 'galleries']] as $key => $default) {
            $list = $values[$key] ?? $default;
            $payload[$key] = implode(',', array_filter(is_array($list) ? $list : [], 'is_string'));
        }
        $payload['min_anti_ai_score'] = (int) ($values['min_anti_ai_score'] ?? 85);
        DB::transaction(fn () => $this->settingsService->setMany('content_audit', $payload));
    }
}
