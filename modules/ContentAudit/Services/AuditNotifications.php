<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Models\ContentAudit;

final class AuditNotifications
{
    public function __construct(private readonly ModuleSettingsServiceInterface $settingsService, private readonly ModuleNotificationServiceInterface $notificationService, private readonly AuditEntityPresentation $presentation, private readonly AuditRevisionService $revisions) {}

    public function submitted(Model $entity, User $author, ?string $notes = null): void
    {
        $notifyInApp = (bool) $this->settingsService->get('content_audit', 'notify_in_app', true);
        $notifyEmail = (bool) $this->settingsService->get('content_audit', 'notify_email', false);
        if (! $notifyInApp && ! $notifyEmail) {
            return;
        }

        $typeSlug = $this->presentation->slug($entity);
        $title = $this->presentation->title($entity);

        $actionUrl = route('admin.audit.review', [
            'type' => $typeSlug,
            'id' => $entity->getKey(),
        ]);

        User::query()->where('is_service_account', false)->with('roles.permissions')->each(function (User $recipient) use ($entity, $author, $notes, $typeSlug, $title, $actionUrl, $notifyInApp, $notifyEmail): void {
            if (! app(EditorialVisibilityInterface::class)->canReview($recipient) || ! app(CapabilityAuthorizer::class)->allowsUser($recipient, [], 'write', $this->presentation->permission($entity), 'web')) {
                return;
            }
            $this->notificationService->notify(
                module: 'content_audit',
                type: 'submission',
                title: 'Nuevo contenido pendiente de auditoría',
                message: "{$author->name} ha enviado a revisión el {$typeSlug}: '{$title}'.",
                actionUrl: $actionUrl,
                data: [
                    'required_permission' => $this->presentation->permission($entity),
                    'audit_version' => $this->revisions->auditFor($entity)?->current_version,
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

    public function decided(
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

        $typeSlug = $this->presentation->slug($entity);
        $title = $this->presentation->title($entity);

        $entityAttrs = $entity->getAttributes();
        $targetUserId = $audit->submitted_by ?? ($entityAttrs['user_id'] ?? null);
        /** @var User|null $targetUser */
        $targetUser = is_numeric($targetUserId) ? User::find((int) $targetUserId) : null;

        if (! $targetUser instanceof User) {
            return;
        }

        $authorName = $targetUser->name;

        $actionUrl = route('admin.audit.index');
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model && $schema->editView !== null && Route::has($schema->editView)) {
                $actionUrl = route($schema->editView, $entity->getKey());
                break;
            }
        }

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
                'required_permission' => $this->presentation->permission($entity),
                'audit_version' => $this->revisions->auditFor($entity)?->current_version,
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
}
