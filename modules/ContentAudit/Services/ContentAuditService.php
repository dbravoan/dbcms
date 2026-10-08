<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Contracts\Sectionable;
use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Search\SearchIndexer;
use App\Core\Sections\SectionEditorService;
use App\Core\Sections\SectionSyncService;
use App\Models\User;
use App\Services\Ai\AntiAiEditorialService;
use App\Services\Capabilities\ContentSnapshotService;
use App\Services\Cms\PageCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\ContentAudit\Contracts\Services\DiffEngineServiceInterface;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Enums\AuditAction;
use Modules\ContentAudit\Enums\AuditStatus;
use Modules\ContentAudit\Events\ContentApprovedEvent;
use Modules\ContentAudit\Events\ContentRejectedEvent;
use Modules\ContentAudit\Events\ContentSubmittedForReviewEvent;
use Modules\ContentAudit\Models\ContentAudit;
use Modules\ContentAudit\Models\ContentAuditRevision;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageTranslation;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductTranslation;

class ContentAuditService implements ContentAuditServiceInterface
{
    public function __construct(
        protected ModuleSettingsServiceInterface $settingsService,
        protected ModuleNotificationServiceInterface $notificationService,
        protected DiffEngineServiceInterface $diffEngine,
        protected PageCacheService $pageCache,
        protected AntiAiEditorialService $antiAi
    ) {}

    public function isEnabled(): bool
    {
        try {
            return app(ModuleManager::class)->isModuleEnabled('content_audit')
                && (bool) $this->settingsService->get('content_audit', 'is_enabled', true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function canPublishDirectly(?User $user = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->hasPermission('audit:approve') || $user->hasPermission('manage:modules')) {
            return true;
        }

        $approverRoles = $this->getConfigList('approver_roles', ['admin', 'editor']);

        return $user->hasRole($approverRoles);
    }

    public function requiresAudit(Model $entity, ?User $user = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $user = $user ?? Auth::user();
        if (! $user) {
            return true;
        }

        if ($this->canPublishDirectly($user)) {
            return false;
        }

        $entityType = $this->resolveEntitySlug($entity);
        $auditedEntities = $this->getConfigList('audited_entities', ['posts', 'pages', 'products', 'galleries']);

        if (! in_array($entityType, $auditedEntities, true)) {
            return false;
        }

        $auditedRoles = $this->getConfigList('audited_roles', ['author', 'marketing']);

        return $user->hasRole($auditedRoles);
    }

    public function getAudit(Model $entity): ?ContentAudit
    {
        return ContentAudit::where('auditable_type', $entity->getMorphClass())
            ->where('auditable_id', $entity->getKey())
            ->first();
    }

    public function submitForReview(Model $entity, User $author, ?string $notes = null): ContentAudit
    {
        return DB::transaction(function () use ($entity, $author, $notes): ContentAudit {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            // Keep public status un-published
            if (! $this->getAudit($entity)?->pending_revision_id) {
                $this->setPublishedFlag($entity, false);
                $this->invalidateEntityCache($entity);
            }

            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::DRAFT->value;

            $audit->status = AuditStatus::PENDING_REVIEW->value;
            $audit->submitted_by = $author->id;
            $audit->submitted_at = now();
            $audit->notes = $notes;
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->save();

            $this->recordChange(
                $entity,
                $author,
                AuditAction::SUBMITTED->value,
                $statusBefore,
                AuditStatus::PENDING_REVIEW->value,
                $notes
            );

            // Dispatch domain event
            DB::afterCommit(function () use ($entity, $audit, $author, $notes): void {
                event(new ContentSubmittedForReviewEvent($entity, $audit, $author, $notes));
                $this->notifyApprovers($entity, $author, $notes);
            });

            return $audit;
        });
    }

    public function saveAsDraft(Model $entity, User $author): ContentAudit
    {
        return DB::transaction(function () use ($entity, $author): ContentAudit {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            // Drafts never face the public site.
            if (! $this->getAudit($entity)?->pending_revision_id) {
                $this->setPublishedFlag($entity, false);
                $this->invalidateEntityCache($entity);
            }

            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::DRAFT->value;

            $audit->status = AuditStatus::DRAFT->value;
            $audit->submitted_by = $author->id;
            $audit->submitted_at = null;
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->save();

            $this->recordChange(
                $entity,
                $author,
                $entity->wasRecentlyCreated ? AuditAction::CREATED->value : AuditAction::UPDATED->value,
                $statusBefore,
                AuditStatus::DRAFT->value
            );

            return $audit;
        });
    }

    /** @param array<string,mixed> $snapshot */
    public function stageSnapshot(Model $entity, User $author, array $snapshot, string $liveHash, bool $submit = false, ?string $notes = null): ContentAudit
    {
        return DB::transaction(function () use ($entity, $author, $snapshot, $liveHash, $submit, $notes): ContentAudit {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $this->enforceEditorialSnapshot($entity, $author, $snapshot);
            $audit = ContentAudit::firstOrCreate(['auditable_type' => $entity->getMorphClass(), 'auditable_id' => $entity->getKey()], ['status' => AuditStatus::DRAFT->value, 'current_version' => 0]);
            $revision = $this->recordChange($entity, $author, AuditAction::UPDATED->value, $audit->status, AuditStatus::DRAFT->value);
            $snapshot += $this->captureSnapshot($entity);
            $snapshot['_live_hash'] = $liveHash;
            $revision->snapshot = $snapshot;
            $revision->save();
            $audit->fill(['status' => AuditStatus::DRAFT->value, 'submitted_by' => $author->getKey(), 'submitted_at' => null, 'pending_revision_id' => $revision->getKey(), 'current_version' => $revision->version])->save();

            return $submit ? $this->submitForReview($entity, $author, $notes) : $audit;
        });
    }

    public function isEditorialEnforcementEnabled(): bool
    {
        try {
            return (bool) $this->settingsService->get('content_audit', 'enforce_editorial_quality', false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function minEditorialScore(): int
    {
        try {
            return max(0, min(100, (int) $this->settingsService->get('content_audit', 'min_anti_ai_score', 85)));
        } catch (\Throwable) {
            return 85;
        }
    }

    public function enforceEditorialQuality(Model $entity, User $user, bool $requiresAudit): void
    {
        if (! $this->isEditorialEnforcementEnabled() || ! $requiresAudit) {
            return;
        }

        [$title, $content] = $this->requestEditorialPayload();

        if ($title === '' || trim($content) === '') {
            // Nothing assessable in this save path (no translation payload).
            return;
        }

        $result = $this->antiAi->validateEditorialTone($title, $content);
        $min = $this->minEditorialScore();

        $clicheFree = $result['cliches_found'] === [];
        if ($result['anti_ai_score'] >= $min && $clicheFree) {
            return;
        }

        $reasons = [];
        if (! $clicheFree) {
            $reasons[] = 'Expresiones cliché detectadas: '.implode(', ', array_slice($result['cliches_found'], 0, 3)).'.';
        }
        foreach ($result['recommendations'] as $recommendation) {
            $reasons[] = $recommendation;
        }

        throw ValidationException::withMessages([
            'editorial_quality' => 'El contenido no supera el control de calidad editorial (puntuación '
                .$result['anti_ai_score'].' de '.$min.'). '.implode(' ', array_slice($reasons, 0, 2)),
        ]);
    }

    /** @param array<string,mixed> $snapshot */
    public function enforceEditorialSnapshot(Model $entity, User $user, array $snapshot): void
    {
        if (! $this->isEnabled() || ! $this->isEditorialEnforcementEnabled() || ! $this->requiresAudit($entity, $user)) {
            return;
        }
        $payloads = [];
        foreach ((array) ($snapshot['translation_records'] ?? []) as $translation) {
            if (! is_array($translation)) {
                continue;
            }
            $locale = (string) ($translation['locale'] ?? 'es');
            $payloads[] = [$locale, (string) ($translation['title'] ?? $translation['name'] ?? ''), $this->editorialText($translation)];
        }
        foreach ((array) ($snapshot['section_records'] ?? []) as $section) {
            foreach ((array) ($section['translations'] ?? []) as $translation) {
                $content = $translation['content_json'] ?? [];
                if (is_string($content)) {
                    $content = json_decode($content, true);
                }
                if (is_array($content)) {
                    $payloads[] = [(string) ($translation['locale'] ?? 'es'), (string) ($content['title'] ?? ''), $this->editorialText($content)];
                }
            }
        }
        foreach ($payloads as [$locale, $title, $content]) {
            if (trim($content) === '') {
                continue;
            }
            $result = $this->antiAi->validateEditorialTone($title, $content, $locale);
            if ($result['anti_ai_score'] < $this->minEditorialScore() || $result['cliches_found'] !== []) {
                throw ValidationException::withMessages(['editorial_quality' => "El texto en {$locale} no supera la revisión de estilo (heurística {$result['anti_ai_score']}/{$this->minEditorialScore()}). ".implode(' ', array_slice($result['recommendations'], 0, 2))]);
            }
        }
    }

    /** @param array<string,mixed> $values */
    private function editorialText(array $values): string
    {
        $text = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $text[] = $this->editorialText($value);
            } elseif (is_string($value) && in_array($key, ['title', 'name', 'content', 'text', 'summary', 'description', 'subtitle', 'heading', 'question', 'answer', 'caption', 'label', 'submit_button_text', 'success_message'], true)) {
                $text[] = strip_tags($value);
            }
        }

        return implode("\n", $text);
    }

    public function recordDeletion(Model $entity, User $actor): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        DB::transaction(function () use ($entity, $actor): void {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $audit = ContentAudit::firstOrNew(['auditable_type' => $entity->getMorphClass(), 'auditable_id' => $entity->getKey()]);
            $before = $audit->getAttributes()['status'] ?? AuditStatus::DRAFT->value;
            $audit->status = AuditStatus::DELETED->value;
            $audit->pending_revision_id = null;
            $audit->current_version = (int) ($audit->current_version ?? 0) + 1;
            $audit->save();
            $this->recordChange($entity, $actor, AuditAction::DELETED->value, $before, AuditStatus::DELETED->value);
        });
    }

    public function latestRevisionId(Model $entity): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        return (int) ContentAuditRevision::query()->where('auditable_type', $entity->getMorphClass())->where('auditable_id', $entity->getKey())->max('id');
    }

    public function completeMutation(Model $entity, User $actor, int $previousRevisionId, ?string $editorialAction = null, ?string $notes = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        $snapshot = $this->captureSnapshot($entity);
        $this->enforceEditorialSnapshot($entity, $actor, $snapshot);
        $restricted = $this->requiresAudit($entity, $actor);
        $state = $this->getAudit($entity)?->status;
        if (($editorialAction === 'submit_for_review' || ($restricted && $editorialAction !== 'draft')) && $state !== AuditStatus::PENDING_REVIEW->value) {
            $this->submitForReview($entity, $actor, $notes);
        } elseif ($editorialAction === 'draft' && $state !== AuditStatus::DRAFT->value) {
            $this->saveAsDraft($entity, $actor);
        }
        $snapshot = $this->captureSnapshot($entity);
        $latest = ContentAuditRevision::query()->where('auditable_type', $entity->getMorphClass())->where('auditable_id', $entity->getKey())->orderByDesc('version')->first();
        if ($latest === null || $latest->getKey() <= $previousRevisionId) {
            if ($this->requiresAudit($entity, $actor)) {
                $this->saveAsDraft($entity, $actor);
            } elseif ((bool) $this->settingsService->get('content_audit', 'track_all_modifications', true)) {
                $this->recordChange($entity, $actor, AuditAction::UPDATED->value);
            }

            return;
        }
        $previous = ContentAuditRevision::query()->where('auditable_type', $entity->getMorphClass())->where('auditable_id', $entity->getKey())->where('version', '<', $latest->version)->orderByDesc('version')->first();
        $latest->snapshot = $snapshot;
        $latest->title = Str::limit($this->resolveEntityTitle($entity), 250);
        $latest->summary = $this->resolveActionSummary($latest->action, $actor, $latest->title);
        $latest->diff = $previous === null ? null : $this->diffEngine->computeDiff((array) $previous->snapshot, $snapshot)->toArray();
        $latest->save();
    }

    public function approve(Model $entity, User $auditor, ?string $notes = null, bool $publish = false): ContentAudit
    {
        $this->assertReviewPermission($entity, $auditor);

        return DB::transaction(function () use ($entity, $auditor, $notes, $publish): ContentAudit {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $entity->setRawAttributes($locked->getAttributes(), true);
            $entity->unsetRelations();
            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::PENDING_REVIEW->value;
            $approvedVisibility = null;
            $appliedRevision = false;

            $autoPublish = $publish || (bool) $this->settingsService->get('content_audit', 'auto_publish_on_approval', true);
            if ($audit->pending_revision_id !== null && $autoPublish) {
                $revision = $audit->revisions()->whereKey($audit->pending_revision_id)->firstOrFail();
                $snapshots = app(ContentSnapshotService::class);
                abort_unless(($revision->snapshot['_live_hash'] ?? null) === $snapshots->hash($snapshots->capture($entity->refresh())), 409, 'The live content changed after this draft was created.');
                $snapshots->restore($entity, $revision->snapshot);
                $appliedRevision = true;
                $intent = $revision->snapshot['_visibility_intent'] ?? null;
                if (is_array($intent) && in_array($intent['field'] ?? null, ['is_published', 'is_active'], true) && is_bool($intent['value'] ?? null)) {
                    $approvedVisibility = $intent['value'];
                } else {
                    // Older staged revisions carry the requested visibility in their attributes.
                    foreach (['is_published', 'is_active'] as $flag) {
                        if (array_key_exists($flag, $revision->snapshot['attributes'] ?? [])) {
                            $approvedVisibility = (bool) $revision->snapshot['attributes'][$flag];
                            break;
                        }
                    }
                }
                $audit->pending_revision_id = null;
            }

            $audit->status = AuditStatus::APPROVED->value;
            $audit->reviewed_by = $auditor->id;
            $audit->reviewed_at = now();
            $audit->rejection_reason = null;
            if ($notes !== null) {
                $audit->notes = $notes;
            }
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->save();

            if ($autoPublish) {
                $this->setPublishedFlag($entity, $approvedVisibility ?? true);
                app(SectionSyncService::class)->publish($entity);
            }
            if ($autoPublish || $appliedRevision) {
                $this->invalidateEntityCache($entity);
            }

            $this->recordChange(
                $entity,
                $auditor,
                AuditAction::APPROVED->value,
                $statusBefore,
                AuditStatus::APPROVED->value,
                $notes
            );

            // Dispatch domain event
            DB::afterCommit(function () use ($entity, $audit, $auditor, $notes): void {
                event(new ContentApprovedEvent($entity, $audit, $auditor, $notes));
                $this->notifyAuthorDecision($entity, $audit, $auditor, true);
            });

            return $audit;
        });
    }

    public function reject(Model $entity, User $auditor, string $reason, ?string $notes = null): ContentAudit
    {
        $this->assertReviewPermission($entity, $auditor);

        return DB::transaction(function () use ($entity, $auditor, $reason, $notes): ContentAudit {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $entity->setRawAttributes($locked->getAttributes(), true);
            $entity->unsetRelations();
            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::PENDING_REVIEW->value;
            $hadPending = $audit->pending_revision_id !== null;

            $audit->status = AuditStatus::REJECTED->value;
            $audit->reviewed_by = $auditor->id;
            $audit->reviewed_at = now();
            $audit->rejection_reason = $reason;
            if ($notes !== null) {
                $audit->notes = $notes;
            }
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->save();

            // Ensure unpublished
            if (! $hadPending) {
                $this->setPublishedFlag($entity, false);
                $this->invalidateEntityCache($entity);
            }

            $this->recordChange(
                $entity,
                $auditor,
                AuditAction::REJECTED->value,
                $statusBefore,
                AuditStatus::REJECTED->value,
                "Motivo de rechazo: {$reason}"
            );

            if ($hadPending) {
                // Archive the rejected snapshot first, then allow a fresh draft from live content.
                $audit->pending_revision_id = null;
                $audit->save();
            }

            // Dispatch domain event
            DB::afterCommit(function () use ($entity, $audit, $auditor, $reason, $notes): void {
                event(new ContentRejectedEvent($entity, $audit, $auditor, $reason, $notes));
                $this->notifyAuthorDecision($entity, $audit, $auditor, false, $reason);
            });

            return $audit;
        });
    }

    public function recordChange(
        Model $entity,
        ?User $user,
        string $action,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $notes = null
    ): ContentAuditRevision {
        return DB::transaction(function () use ($entity, $user, $action, $statusBefore, $statusAfter, $notes): ContentAuditRevision {
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $audit = $this->getAudit($entity);

            // Previous revision for diff comparison
            /** @var ContentAuditRevision|null $lastRevision */
            $lastRevision = ContentAuditRevision::where('auditable_type', $entity->getMorphClass())
                ->where('auditable_id', $entity->getKey())
                ->orderByDesc('version')
                ->first();

            $nextVersion = ($lastRevision->version ?? 0) + 1;

            $pending = $audit?->pending_revision_id !== null ? $audit->revisions()->whereKey($audit->pending_revision_id)->first() : null;
            $newSnapshot = $pending->snapshot ?? $this->captureSnapshot($entity);

            $diffPayload = null;
            if ($lastRevision && is_array($lastRevision->snapshot)) {
                $diffResult = $this->diffEngine->computeDiff($lastRevision->snapshot, $newSnapshot);
                $diffPayload = $diffResult->toArray();
            }

            $title = $this->resolveEntityTitle($entity);

            return ContentAuditRevision::create([
                'content_audit_id' => $audit?->id,
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
                'user_id' => $user?->id,
                'version' => $nextVersion,
                'action' => $action,
                'status_before' => $statusBefore,
                'status_after' => $statusAfter,
                'title' => Str::limit($title, 250),
                'summary' => $this->resolveActionSummary($action, $user, $title),
                'snapshot' => $newSnapshot,
                'diff' => $diffPayload,
                'notes' => $notes,
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 250),
            ]);
        });
    }

    public function rollback(Model $entity, int $targetVersion, User $auditor): ContentAudit
    {
        $this->assertReviewPermission($entity, $auditor);

        return DB::transaction(function () use ($entity, $targetVersion, $auditor): ContentAudit {
            /** @var ContentAuditRevision $targetRevision */
            $targetRevision = ContentAuditRevision::where('auditable_type', $entity->getMorphClass())
                ->where('auditable_id', $entity->getKey())
                ->where('version', $targetVersion)
                ->firstOrFail();

            $snapshot = (array) $targetRevision->snapshot;

            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $entity->setRawAttributes($locked->getAttributes(), true);
            $entity->unsetRelations();
            if (isset($snapshot['attributes'])) {
                app(ContentSnapshotService::class)->restore($entity, $snapshot);
            } else {
                foreach (['is_published', 'category_id', 'template', 'published_at', 'sku', 'price'] as $field) {
                    if (array_key_exists($field, $snapshot) && array_key_exists($field, $entity->getAttributes())) {
                        $entity->setAttribute($field, $snapshot[$field]);
                    }
                }
                $entity->saveQuietly();
                if (isset($snapshot['translations']) && is_array($snapshot['translations'])) {
                    $this->restoreTranslations($entity, $snapshot['translations']);
                }
                if (isset($snapshot['sections']) && is_array($snapshot['sections'])) {
                    $this->restoreSections($entity, $snapshot['sections']);
                }
            }
            $entity->setUpdatedAt(now());
            $entity->saveQuietly();

            /** @var ContentAudit $audit */
            $audit = ContentAudit::firstOrNew([
                'auditable_type' => $entity->getMorphClass(),
                'auditable_id' => $entity->getKey(),
            ]);

            $statusBefore = $audit->status ?? AuditStatus::DRAFT->value;
            $audit->pending_revision_id = null;
            $audit->status = AuditStatus::DRAFT->value;
            $audit->current_version = ($audit->current_version ?? 0) + 1;
            $audit->notes = "Revertido a la versión {$targetVersion} por {$auditor->name}";
            $audit->save();

            $this->invalidateEntityCache($entity);

            $this->recordChange(
                $entity,
                $auditor,
                AuditAction::ROLLBACK->value,
                $statusBefore,
                AuditStatus::DRAFT->value,
                "Restaurada versión histórica #{$targetVersion}"
            );

            return $audit;
        });
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    protected function restoreTranslations(Model $entity, array $translations): void
    {
        if ($entity instanceof Post) {
            foreach ($translations as $locale => $t) {
                PostTranslation::updateOrCreate(
                    ['post_id' => $entity->id, 'locale' => $locale],
                    [
                        'title' => $t['title'] ?? '',
                        'slug' => $t['slug'] ?? '',
                        'summary' => $t['summary'] ?? '',
                        'content' => $t['content'] ?? '',
                        'meta_title' => $t['meta_title'] ?? '',
                        'meta_description' => $t['meta_description'] ?? '',
                    ]
                );
            }
        } elseif ($entity instanceof Page) {
            foreach ($translations as $locale => $t) {
                PageTranslation::updateOrCreate(
                    ['page_id' => $entity->id, 'locale' => $locale],
                    [
                        'title' => $t['title'] ?? '',
                        'slug' => $t['slug'] ?? '',
                        'summary' => $t['summary'] ?? '',
                        'content' => $t['content'] ?? '',
                        'meta_title' => $t['meta_title'] ?? '',
                        'meta_description' => $t['meta_description'] ?? '',
                    ]
                );
            }
        } elseif ($entity instanceof Product) {
            foreach ($translations as $locale => $t) {
                ProductTranslation::updateOrCreate(
                    ['product_id' => $entity->id, 'locale' => $locale],
                    [
                        'name' => $t['title'] ?? ($t['name'] ?? ''),
                        'slug' => $t['slug'] ?? '',
                        'description' => $t['summary'] ?? ($t['description'] ?? ''),
                        'content' => $t['content'] ?? '',
                        'meta_title' => $t['meta_title'] ?? '',
                        'meta_description' => $t['meta_description'] ?? '',
                    ]
                );
            }
        }
    }

    protected function notifyApprovers(Model $entity, User $author, ?string $notes = null): void
    {
        $notifyInApp = (bool) $this->settingsService->get('content_audit', 'notify_in_app', true);
        $notifyEmail = (bool) $this->settingsService->get('content_audit', 'notify_email', false);
        if (! $notifyInApp && ! $notifyEmail) {
            return;
        }

        $typeSlug = $this->resolveEntitySlug($entity);
        $title = $this->resolveEntityTitle($entity);

        $actionUrl = route('admin.audit.review', [
            'type' => $typeSlug,
            'id' => $entity->getKey(),
        ]);

        User::query()->where('is_service_account', false)->with('roles.permissions')->each(function (User $recipient) use ($entity, $author, $notes, $typeSlug, $title, $actionUrl, $notifyInApp, $notifyEmail): void {
            if (! app(EditorialVisibilityInterface::class)->canReview($recipient) || ! app(CapabilityAuthorizer::class)->allowsUser($recipient, [], 'write', $this->permissionForEntity($entity), 'web')) {
                return;
            }
            $this->notificationService->notify(
                module: 'content_audit',
                type: 'submission',
                title: 'Nuevo contenido pendiente de auditoría',
                message: "{$author->name} ha enviado a revisión el {$typeSlug}: '{$title}'.",
                actionUrl: $actionUrl,
                data: [
                    'required_permission' => $this->permissionForEntity($entity),
                    'audit_version' => $this->getAudit($entity)?->current_version,
                    'entity_type' => $typeSlug,
                    'entity_id' => $entity->getKey(),
                    'author_id' => $author->id,
                    'author_name' => $author->name,
                    'notes' => $notes,
                ],
                sendEmail: $notifyEmail,
                inApp: $notifyInApp,
                userId: (int) $recipient->getKey(),
            );
        });
    }

    protected function notifyAuthorDecision(
        Model $entity,
        ContentAudit $audit,
        User $auditor,
        bool $isApproved,
        ?string $reason = null
    ): void {
        $notifyInApp = (bool) $this->settingsService->get('content_audit', 'notify_in_app', true);
        $notifyEmail = (bool) $this->settingsService->get('content_audit', 'notify_email', false);
        if (! $notifyInApp && ! $notifyEmail) {
            return;
        }

        $typeSlug = $this->resolveEntitySlug($entity);
        $title = $this->resolveEntityTitle($entity);

        $entityAttrs = $entity->getAttributes();
        $targetUserId = $audit->submitted_by ?? ($entityAttrs['user_id'] ?? null);
        /** @var User|null $targetUser */
        $targetUser = is_numeric($targetUserId) ? User::find((int) $targetUserId) : null;

        if (! $targetUser instanceof User) {
            return;
        }

        $authorName = $targetUser->name;

        $actionUrl = match ($typeSlug) {
            'posts' => route('admin.blog.posts.edit', $entity->getKey()),
            'pages' => route('admin.pages.edit', $entity->getKey()),
            'products' => route('admin.shop.products.edit', $entity->getKey()),
            default => route('admin.audit.index'),
        };

        if ($isApproved) {
            $msgTitle = 'Contenido aprobado y publicado';
            $isPublic = $audit->pending_revision_id === null && (bool) ($entityAttrs['is_published'] ?? $entityAttrs['is_active'] ?? false);
            $msgTitle = $isPublic ? 'Contenido aprobado y publicado' : 'Cambios aprobados';
            $message = $isPublic
                ? "¡Enhorabuena, {$authorName}! Tu {$typeSlug} '{$title}' ha sido aprobado por {$auditor->name} y publicado."
                : "Los cambios de tu {$typeSlug} '{$title}' han sido aprobados por {$auditor->name}. La revisión queda aprobada y pendiente de publicación explícita.";
        } else {
            $msgTitle = 'Se requieren cambios en tu contenido';
            $message = "Hola {$authorName}, tu {$typeSlug} '{$title}' ha sido revisado por {$auditor->name}. Motivo: {$reason}";
        }

        $this->notificationService->notify(
            module: 'content_audit',
            type: $isApproved ? 'approval' : 'rejection',
            title: $msgTitle,
            message: $message,
            actionUrl: $actionUrl,
            data: [
                'required_permission' => $this->permissionForEntity($entity),
                'audit_version' => $this->getAudit($entity)?->current_version,
                'entity_type' => $typeSlug,
                'entity_id' => $entity->getKey(),
                'auditor_id' => $auditor->id,
                'is_approved' => $isApproved,
                'reason' => $reason,
            ],
            sendEmail: $notifyEmail,
            inApp: $notifyInApp,
            userId: (int) $targetUser->getKey(),
        );
    }

    public function resolveEntitySlug(Model $entity): string
    {
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model) {
                return Str::plural($schema->type);
            }
        }

        return Str::plural(Str::snake(class_basename($entity)));
    }

    public function resolveEntityFromSlug(string $type, int|string $id): ?Model
    {
        $alias = strtolower($type);
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if (! in_array($alias, [$schema->type, Str::plural($schema->type), strtolower($schema->model)], true)) {
                continue;
            }
            /** @var class-string<Model> $class */
            $class = $schema->model;
            $model = new $class;
            $query = $model->newQuery();
            if (method_exists($model, 'translations')) {
                $query->with('translations');
            }

            return $query->find($id);
        }

        return null;
    }

    /**
     * Captures a unified historical snapshot of an entity including its translations.
     *
     * @return array<string, mixed>
     */
    public function captureSnapshot(Model $entity): array
    {
        if (method_exists($entity, 'captureAuditSnapshot')) {
            return $entity->captureAuditSnapshot() + app(ContentSnapshotService::class)->capture($entity);
        }

        $attrs = $entity->getAttributes();
        $base = [
            'id' => $entity->getKey(),
            'is_published' => (bool) ($attrs['is_published'] ?? $attrs['is_active'] ?? false),
            'published_at' => ! empty($attrs['published_at']) ? (string) $attrs['published_at'] : null,
        ];

        if (array_key_exists('category_id', $attrs)) {
            $base['category_id'] = $attrs['category_id'];
        }

        if (array_key_exists('template', $attrs)) {
            $base['template'] = $attrs['template'];
        }

        if (array_key_exists('sku', $attrs)) {
            $base['sku'] = $attrs['sku'];
            $base['price'] = $attrs['price'] ?? 0;
        }

        $translations = [];
        foreach ($this->translationAttributes($entity) as $locale => $translation) {
            unset($translation['locale']);
            $translations[$locale] = $translation;
        }

        $base['translations'] = $translations;
        $base += app(ContentSnapshotService::class)->capture($entity);

        // Block-manager sections (locale-keyed) are part of the audited content.
        $sections = $this->sectionSnapshot($entity);
        if ($sections !== []) {
            $base['sections'] = $sections;
        }

        return $base;
    }

    /**
     * Capture the entity's localized block-manager payload, if sectionable.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function sectionSnapshot(Model $entity): array
    {
        if (! $entity instanceof Sectionable) {
            return [];
        }

        return app(SectionEditorService::class)->payload($entity);
    }

    /**
     * Restore the localized block-manager payload from a historical snapshot.
     *
     * @param  array<string, mixed>  $sections
     */
    protected function restoreSections(Model $entity, array $sections): void
    {
        if (! $entity instanceof Sectionable) {
            return;
        }

        app(SectionSyncService::class)->sync($entity, json_encode($sections, JSON_THROW_ON_ERROR), 'published');
    }

    public function describeEntity(Model $entity): array
    {
        $translations = collect($this->translationAttributes($entity))->values();

        $attributes = $entity->getAttributes();
        $locale = app()->getLocale();
        $primary = $translations->firstWhere('locale', $locale) ?? $translations->first();

        return [
            'title' => (string) ($primary['title'] ?? ''),
            'slug' => $primary['slug'] ?? null,
            'is_published' => (bool) ($attributes['is_published'] ?? $attributes['is_active'] ?? false),
            'translations' => $translations,
        ];
    }

    /**
     * Normalize an entity's translations from raw attributes (strict-mode safe).
     *
     * @return array<string, array{locale: string, title: string, slug: string, summary: string, content: string, meta_title: string, meta_description: string}>
     */
    protected function translationAttributes(Model $entity): array
    {
        if (! method_exists($entity, 'translations')) {
            return [];
        }

        $relation = $entity->relationLoaded('translations')
            ? $entity->getRelationValue('translations')
            : $entity->translations()->get();

        if (! $relation instanceof Collection) {
            return [];
        }

        $translations = [];
        foreach ($relation as $translation) {
            if (! $translation instanceof Model) {
                continue;
            }

            $attributes = $translation->getAttributes();
            $locale = (string) ($attributes['locale'] ?? 'es');

            $translations[$locale] = [
                'locale' => $locale,
                'title' => (string) ($attributes['title'] ?? $attributes['name'] ?? ''),
                'slug' => (string) ($attributes['slug'] ?? ''),
                'summary' => (string) ($attributes['summary'] ?? $attributes['description'] ?? ''),
                'content' => (string) ($attributes['content'] ?? ''),
                'meta_title' => (string) ($attributes['meta_title'] ?? ''),
                'meta_description' => (string) ($attributes['meta_description'] ?? ''),
            ];
        }

        return $translations;
    }

    /**
     * Resolves the editorial payload (title + content) of the current request.
     *
     * @return array{0: string, 1: string}
     */
    protected function requestEditorialPayload(): array
    {
        $translations = (array) (request()?->input('translations') ?? []);

        $first = is_array(reset($translations)) ? (array) reset($translations) : [];

        return [
            (string) ($first['title'] ?? ''),
            (string) ($first['content'] ?? ''),
        ];
    }

    /**
     * Drops every cached public page that depends on this entity.
     *
     * Editorial decisions change the public site through `saveQuietly()`, which
     * does not fire the PageCacheObserver, so the invalidation must be explicit.
     */
    protected function invalidateEntityCache(Model $entity): void
    {
        $table = $entity->getTable();
        $id = $entity->getKey();
        $type = null;
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model) {
                $type = $schema->type;
                break;
            }
        }
        DB::afterCommit(function () use ($table, $id, $type): void {
            try {
                $this->pageCache->invalidateDependencies([
                    $this->pageCache->dependencyKey($table, (string) $id),
                    $this->pageCache->typeDependencyKey($table),
                ]);
            } catch (\Throwable) {
                // Cache failures must never undo an editorial decision.
            }
            if ($type !== null) {
                app(SearchIndexer::class)->indexEntity($type, $id);
            }
        });
    }

    /**
     * Toggle the entity's public visibility flag without tripping strict mode.
     *
     * Posts/pages expose `is_published` (+ `published_at`); products use
     * `is_active`. Entities exposing neither are left untouched.
     */
    private function assertReviewPermission(Model $entity, User $auditor): void
    {
        abort_unless(app(ModuleManager::class)->isModuleEnabled('content_audit'), 404);
        $fresh = User::find($auditor->getKey());
        abort_unless($fresh instanceof User && app(EditorialVisibilityInterface::class)->canReview($fresh)
            && app(CapabilityAuthorizer::class)->allowsUser($fresh, [], 'write', $this->permissionForEntity($entity), 'web'), 403);
    }

    private function permissionForEntity(Model $entity): string
    {
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model) {
                return $schema->permission ?? 'access:admin';
            }
        }

        return 'access:admin';
    }

    protected function setPublishedFlag(Model $entity, bool $published): void
    {
        $attributes = $entity->getAttributes();

        if (array_key_exists('is_published', $attributes)) {
            $entity->setAttribute('is_published', $published);
        } elseif (array_key_exists('is_active', $attributes)) {
            $entity->setAttribute('is_active', $published);
        } else {
            return;
        }

        if ($published && array_key_exists('published_at', $attributes) && empty($attributes['published_at'])) {
            $entity->setAttribute('published_at', now());
        }

        $entity->saveQuietly();
    }

    protected function resolveEntityTitle(Model $entity): string
    {
        $translations = $this->translationAttributes($entity);

        if ($translations !== []) {
            $translation = $translations['es'] ?? reset($translations);
            if ($translation['title'] !== '') {
                return $translation['title'];
            }
        }

        $attributes = $entity->getAttributes();

        return (string) ($attributes['title'] ?? $attributes['name'] ?? "Entidad #{$entity->getKey()}");
    }

    protected function resolveActionSummary(string $action, ?User $user, string $title): string
    {
        $userName = $user ? $user->name : 'Sistema';

        return match ($action) {
            AuditAction::CREATED->value => "{$userName} creó el contenido inicial '{$title}'.",
            AuditAction::UPDATED->value => "{$userName} modificó el contenido de '{$title}'.",
            AuditAction::SUBMITTED->value => "{$userName} envió '{$title}' a la cola de auditoría de calidad.",
            AuditAction::APPROVED->value => "{$userName} aprobó y autorizó la publicación de '{$title}'.",
            AuditAction::REJECTED->value => "{$userName} rechazó '{$title}' solicitando subsanaciones.",
            AuditAction::PUBLISHED->value => "{$userName} publicó directamente '{$title}'.",
            AuditAction::UNPUBLISHED->value => "{$userName} despublicó '{$title}'.",
            AuditAction::DELETED->value => "{$userName} eliminó el contenido '{$title}'. Su última versión queda archivada.",
            AuditAction::ROLLBACK->value => "{$userName} revirtió '{$title}' a una versión histórica.",
            default => "{$userName} ejecutó la acción [{$action}] sobre '{$title}'.",
        };
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    protected function getConfigList(string $key, array $default): array
    {
        try {
            $raw = $this->settingsService->get('content_audit', $key, implode(',', $default));
            $parts = array_map('trim', is_array($raw) ? array_filter($raw, 'is_string') : explode(',', is_string($raw) ? $raw : ''));

            return array_values(array_filter($parts));
        } catch (\Throwable) {
            return $default;
        }
    }
}
