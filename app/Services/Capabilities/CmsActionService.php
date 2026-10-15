<?php

declare(strict_types=1);

namespace App\Services\Capabilities;

use App\Contracts\Sectionable;
use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Contracts\Services\SearchAnalyticsInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\AvailableCapabilities;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\ExecutionContext;
use App\Core\Capabilities\Tools\Entity\EntityTypesTool;
use App\Core\Capabilities\Tools\Resource\ResourceTypesTool;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Filters\FilterPathResolver;
use App\Core\Filters\FilterSet;
use App\Core\Modules\Contracts\MaintenanceModeInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Search\SearchIndexer;
use App\Core\Sections\SectionLayout;
use App\Core\Sections\SectionSyncService;
use App\Models\CapabilityAuditLog;
use App\Models\CmsModule;
use App\Models\FilterPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\TokenRevocationService;
use App\Services\Cms\SiteSettingsService;
use App\Services\Cms\ThemeManagerService;
use App\Services\Media\MediaManagerService;
use App\Services\Storage\ExternalStorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

/**
 * Operations that are not CRUD, mirroring the specific buttons of the backoffice:
 * module lifecycle, theme activation, site settings, maintenance mode, storage
 * connection, caches, search index, editorial approval, block sections, media
 * upload, notifications, tokens and the agent audit trail.
 */
class CmsActionService
{
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly ModuleSettingsServiceInterface $moduleSettings,
        private readonly ThemeManagerService $themes,
        private readonly SiteSettingsService $settings,
        private readonly MaintenanceModeInterface $maintenance,
        private readonly ExternalStorageService $storage,
        private readonly SearchIndexer $indexer,
        private readonly EditorialWorkflowInterface $audit,
        private readonly SectionSyncService $sections,
        private readonly MediaManagerService $media,
        private readonly EntityCrudService $entities,
        private readonly FilterPathResolver $filterPaths,
        private readonly CapabilityAuditLogger $auditLogger,
        private readonly ModuleNotificationServiceInterface $notifications,
        private readonly TokenRevocationService $tokens,
        private readonly SearchAnalyticsInterface $searchAnalytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $actor = app(ExecutionContext::class)->actor;

        return [
            'tools' => $actor === null ? [] : app(AvailableCapabilities::class)->manifest($actor),
            'entity_types' => app(EntityTypesTool::class)->handle([])['types'],
            'resources' => app(ResourceTypesTool::class)->handle([])['resources'],
            'abilities' => [
                'read' => 'requires mcp:read',
                'write' => 'requires mcp:write (or the legacy mcp:publish)',
                'destructive' => 'requires mcp:destructive and a human approval of this change in the backoffice',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function moduleToggle(array $args): array
    {
        $identifier = (string) ($args['identifier'] ?? '');
        $module = CmsModule::query()->where('identifier', $identifier)->first();

        if (! $module instanceof CmsModule) {
            throw ValidationException::withMessages(['identifier' => "Unknown module [{$identifier}]."]);
        }

        $enabled = array_key_exists('enabled', $args)
            ? filter_var($args['enabled'], FILTER_VALIDATE_BOOLEAN)
            : ! $module->is_active;

        if ($enabled) {
            $this->modules->activate($identifier);
        } else {
            $this->modules->deactivate($identifier);
        }

        return [
            'identifier' => $identifier,
            'enabled' => $this->modules->isModuleEnabled($identifier),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function moduleInstall(string $identifier): array
    {
        $installed = $this->modules->install($identifier);

        if (! $installed) {
            throw ValidationException::withMessages(['identifier' => "Module [{$identifier}] could not be installed."]);
        }

        return ['identifier' => $identifier, 'installed' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function moduleUninstall(string $identifier): array
    {
        $uninstalled = $this->modules->uninstall($identifier);

        if (! $uninstalled) {
            throw ValidationException::withMessages(['identifier' => "Module [{$identifier}] could not be uninstalled."]);
        }

        return ['identifier' => $identifier, 'uninstalled' => true];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function moduleSettings(string $identifier, array $settings): array
    {
        if (CmsModule::query()->where('identifier', $identifier)->doesntExist()) {
            throw ValidationException::withMessages(['identifier' => "Unknown module [{$identifier}]."]);
        }

        $this->moduleSettings->setMany($identifier, $settings);

        return [
            'identifier' => $identifier,
            'settings' => $this->auditLogger->maskModuleSettings($identifier, $this->moduleSettings->getAll($identifier), '••••••••'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function themeActivate(string $theme): array
    {
        $theme = Str::slug($theme);

        if (! File::isDirectory(base_path("themes/{$theme}"))) {
            throw ValidationException::withMessages(['theme' => "Unknown theme [{$theme}]."]);
        }

        $this->themes->setActiveTheme($theme);

        return ['theme' => $theme, 'activated' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsGet(): array
    {
        $settings = Setting::query()
            ->get()
            ->mapWithKeys(static function (Setting $setting): array {
                $key = (string) $setting->getAttribute('key');

                return [$key => $setting->getAttribute('value')];
            })
            ->all();

        return [
            'settings' => $this->auditLogger->redact($settings),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function settingsUpdate(array $attributes): array
    {
        if ($attributes === []) {
            throw ValidationException::withMessages(['attributes' => 'Nothing to update.']);
        }

        $this->settings->update($attributes);

        return ['updated' => array_keys($attributes)];
    }

    /**
     * @return array<string, mixed>
     */
    public function maintenanceGet(): array
    {
        return [
            'active' => $this->maintenance->isActive(),
            'settings' => $this->maintenance->getSettings(),
            'retry_after_seconds' => $this->maintenance->getRetryAfterSeconds(),
            'ends_at' => $this->maintenance->getEndDateTime()?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function maintenanceToggle(array $args): array
    {
        $enable = array_key_exists('enabled', $args) ? filter_var($args['enabled'], FILTER_VALIDATE_BOOLEAN) : null;

        if ($enable === true) {
            $this->maintenance->enable(
                isset($args['until']) ? (string) $args['until'] : null,
                isset($args['message']) ? (string) $args['message'] : null,
            );
        } elseif ($enable === false) {
            $this->maintenance->disable();
        } else {
            $this->maintenance->toggle();
        }

        return $this->maintenanceGet();
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionGet(): array
    {
        return [
            'enabled' => $this->storage->isEnabled(),
            'configured' => $this->storage->isConfigured(),
            'provider' => $this->storage->provider(),
            'providers' => $this->storage->providerLabels(),
            'disk' => $this->storage->diskName(),
            'configuration' => $this->auditLogger->redact($this->storage->configuration()),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function connectionUpdate(array $attributes): array
    {
        if ($attributes === []) {
            throw ValidationException::withMessages(['attributes' => 'Nothing to update.']);
        }

        $this->storage->save($attributes);

        return $this->connectionGet();
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionTest(): array
    {
        return $this->storage->testConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function cachePurge(): array
    {
        Artisan::call('cms:cache:purge');

        return ['purged' => true, 'output' => trim(Artisan::output())];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function searchReindex(array $args): array
    {
        $type = isset($args['type']) && is_string($args['type']) && $args['type'] !== '' ? $args['type'] : null;
        $locale = isset($args['locale']) && is_string($args['locale']) && $args['locale'] !== '' ? $args['locale'] : null;
        $keep = filter_var($args['keep'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($type !== null) {
            $indexed = $this->indexer->indexSource($type, $locale);

            return ['type' => $type, 'locale' => $locale, 'indexed' => $indexed];
        }

        $counts = $this->indexer->indexAll($locale, fresh: ! $keep);

        return [
            'indexed' => $counts,
            'total' => array_sum($counts),
            'locale' => $locale,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function searchStats(array $args): array
    {
        $locale = isset($args['locale']) && is_string($args['locale']) ? $args['locale'] : null;

        return [
            'coverage' => $this->indexer->stats($locale),
            'top_terms' => $this->searchAnalytics->topTerms($locale, 10),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function auditApprove(array $args): array
    {
        abort_unless($this->audit->canPublishDirectly($this->actor()), 403, 'Editorial approval permission is required.');
        $entity = $this->entityForAudit($args);
        $audit = $this->audit->approve($entity, $this->actor(), isset($args['notes']) ? (string) $args['notes'] : null);

        return [
            'approved' => true,
            'status' => $audit->status,
            'entity' => ['type' => $this->entities->schemaFor((string) $args['type'])->type, 'id' => $entity->getKey()],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function auditReject(array $args): array
    {
        abort_unless($this->audit->canPublishDirectly($this->actor()), 403, 'Editorial approval permission is required.');
        $entity = $this->entityForAudit($args);
        $reason = trim((string) ($args['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to reject a revision.']);
        }

        $audit = $this->audit->reject($entity, $this->actor(), $reason, isset($args['notes']) ? (string) $args['notes'] : null);

        return ['rejected' => true, 'status' => $audit->status];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function auditRollback(array $args): array
    {
        abort_unless($this->audit->canPublishDirectly($this->actor()), 403, 'Editorial approval permission is required.');
        $entity = $this->entityForAudit($args);
        $targetVersion = (int) ($args['target_version'] ?? 0);

        if ($targetVersion < 1) {
            throw ValidationException::withMessages(['target_version' => 'A positive target_version is required.']);
        }

        $audit = $this->audit->rollback($entity, $targetVersion, $this->actor());

        return [
            'rolled_back' => true,
            'current_version' => $audit->currentVersion,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function sectionsGet(array $args): array
    {
        $entity = $this->sectionable($args);

        if (! $entity instanceof Sectionable) {
            throw ValidationException::withMessages([
                'type' => "Entity [{$args['type']}] does not use the block manager.",
            ]);
        }

        $sections = [];
        $sectionModel = $entity->sectionModelClass();

        /** @var Collection<int, Model> $rows */
        $rows = $sectionModel::query()
            ->where($entity->sectionForeignKey(), $entity->getKey())
            ->with('translations')
            ->orderBy('order')
            ->get();

        foreach ($rows as $section) {
            $item = [
                'id' => (int) $section->getKey(),
                'type' => (string) $section->getAttribute('type'),
                'order' => (int) $section->getAttribute('order'),
                'status' => (string) $section->getAttribute('status'),
                // Canonical contract: `columns` (one entry per column).
                'styling' => SectionLayout::normalize((array) $section->getAttribute('styling_json')),
                'translations' => [],
            ];

            /** @var Collection<int, Model> $translations */
            $translations = $section->getRelation('translations');

            foreach ($translations as $translation) {
                $item['translations'][(string) $translation->getAttribute('locale')]
                    = SectionLayout::normalize((array) $translation->getAttribute('content_json'));
            }

            $sections[] = $item;
        }

        return [
            'type' => (string) ($args['type'] ?? ''),
            'id' => $entity->getKey(),
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function sectionsSync(array $args): array
    {
        $entity = $this->sectionable($args);

        $payload = $args['sections'] ?? $args['sections_json'] ?? null;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
        } else {
            $decoded = is_array($payload) ? $payload : null;
        }

        if (! is_array($decoded) || $decoded === []) {
            throw ValidationException::withMessages([
                'sections' => 'Send the blocks as an array (locale-keyed or a plain list) or as a JSON string.',
            ]);
        }

        $requestedStatus = is_string($args['status'] ?? null) ? (string) $args['status'] : 'published';
        $status = in_array($requestedStatus, ['draft', 'published'], true) ? $requestedStatus : 'published';

        $this->sections->sync($entity, json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $status);

        return $this->sectionsGet(['type' => (string) ($args['type'] ?? ''), 'id' => $entity->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function mediaUpload(array $args): array
    {
        $filename = (string) ($args['filename'] ?? '');
        $content = (string) ($args['content_base64'] ?? '');

        if ($filename === '' || $content === '') {
            throw ValidationException::withMessages([
                'content_base64' => 'A filename and the base64 content are required.',
            ]);
        }

        $maxBytes = MediaManagerService::MAX_UPLOAD_KILOBYTES * 1024;
        if (strlen($content) > 4 * (int) ceil($maxBytes / 3)) {
            throw ValidationException::withMessages(['content_base64' => 'El archivo supera el límite de 50 MB.']);
        }

        $binary = base64_decode($content, true);

        if ($binary === false || strlen($binary) > $maxBytes) {
            throw ValidationException::withMessages(['content_base64' => 'The content is not valid base64.']);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'mcp-media-');

        if ($temporary === false) {
            throw ValidationException::withMessages(['content_base64' => 'The upload could not be prepared.']);
        }

        file_put_contents($temporary, $binary);

        try {
            $file = new UploadedFile(
                $temporary,
                $filename,
                (string) (mime_content_type($temporary) ?: 'application/octet-stream'),
                null,
                true,
            );

            $uploaded = $this->media->upload($file);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return ['uploaded' => true, 'media' => $uploaded];
    }

    /**
     * @return array<string, mixed>
     */
    public function notificationsMarkAllRead(): array
    {
        $updated = $this->notifications->markAllAsRead();

        return ['marked_read' => $updated];
    }

    /**
     * @return array<string, mixed>
     */
    public function tokenRevoke(string $id): array
    {
        $token = Passport::token()->find($id);

        if ($token === null) {
            throw ValidationException::withMessages(['id' => "No token found with id [{$id}]."]);
        }

        $this->tokens->forTokens([$id]);

        return ['id' => $id, 'revoked' => true];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function auditLog(array $args): array
    {
        $actor = app(ExecutionContext::class)->actor;
        if ($actor === null) {
            $id = Auth::guard('web')->id();
            $actor = new ActorContext($id === null ? null : (int) $id);
        }
        abort_unless(app(CapabilityAuthorizer::class)->allowed($actor, 'read', 'manage:modules'), 403);
        $limit = (int) ($args['limit'] ?? 20);
        $tool = isset($args['tool']) && is_string($args['tool']) && $args['tool'] !== '' ? $args['tool'] : null;
        $problemsOnly = filter_var($args['problems_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'stats' => $this->auditLogger->stats(),
            'entries' => $this->auditLogger->recent($limit, $tool, $problemsOnly)
                ->map(static fn (CapabilityAuditLog $log): array => [
                    'id' => (int) $log->getKey(),
                    'tool' => (string) $log->getAttribute('tool'),
                    'status' => (string) $log->getAttribute('status'),
                    'ability' => (string) $log->getAttribute('ability'),
                    'permission' => $log->getAttribute('permission'),
                    'user' => $log->user?->email,
                    'is_m2m' => (bool) $log->getAttribute('is_m2m'),
                    'args' => $log->getAttribute('args'),
                    'error' => $log->getAttribute('error'),
                    'duration_ms' => $log->getAttribute('duration_ms'),
                    'created_at' => $log->getAttribute('created_at')?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Curates (or de-indexes) a filter landing from its friendly path, exactly
     * like the "Indexar en Google" button of the front office.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function seoFilterUpsert(array $args): array
    {
        $module = (string) ($args['module'] ?? '');
        $path = (string) ($args['path'] ?? '');
        $marketCode = (string) ($args['market'] ?? '');
        $locale = (string) ($args['locale'] ?? app()->getLocale());

        $resolved = $this->filterPaths->resolve($module, $marketCode, $locale, $path);

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'path' => 'The path is not a valid, unambiguous filter path within the SEO limits.',
            ]);
        }

        /** @var array{set: FilterSet, canonical: string} $resolved */
        $page = FilterPage::query()->updateOrCreate(
            [
                'module' => $module,
                'market_code' => $marketCode !== '' ? $marketCode : null,
                'locale' => $locale,
                'signature' => $resolved['set']->signature(),
            ],
            [
                'path' => $resolved['canonical'],
                'title' => $args['title'] ?? null,
                'heading' => $args['heading'] ?? null,
                'meta_description' => $args['meta_description'] ?? null,
                'is_indexable' => filter_var($args['is_indexable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        );

        return [
            'id' => (int) $page->getKey(),
            'module' => $module,
            'path' => (string) $page->getAttribute('path'),
            'signature' => (string) $page->getAttribute('signature'),
            'is_indexable' => (bool) $page->getAttribute('is_indexable'),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function entityForAudit(array $args): Model
    {
        $type = (string) ($args['type'] ?? '');
        $id = $args['id'] ?? 0;

        return $this->entities->resolveEntity($type, $id);
    }

    /** @param array<string,mixed> $args */
    public function entityPermission(array $args): ?string
    {
        return empty($args['type']) ? null : ($this->entities->schemaFor((string) $args['type'])->permission ?? 'access:admin');
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function sectionable(array $args): Model
    {
        return $this->entities->resolveEntity((string) ($args['type'] ?? ''), $args['id'] ?? 0);
    }

    private function actor(): User
    {
        $user = request()->user() ?? Auth::user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user' => 'This operation needs a user-backed token (a personal access token), not a machine client.',
            ]);
        }

        return $user;
    }
}
