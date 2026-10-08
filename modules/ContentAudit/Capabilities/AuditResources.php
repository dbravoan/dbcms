<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Capabilities;

use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\ExecutionContext;
use App\Core\Capabilities\Resources\CapabilityResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Models\ContentAudit;

final class AuditResources
{
    public static function descriptor(): CapabilityResource
    {
        return new CapabilityResource(
            key: 'audit',
            label: 'Revisión de contenido',
            model: ContentAudit::class,
            permission: 'access:admin',
            description: 'Cola de revisión editorial (lectura; aprobar, rechazar y revertir son acciones).',
            fillable: [],
            searchable: ['status', 'auditable_type'],
            columns: ['id', 'auditable_type', 'auditable_id', 'status', 'submitted_by', 'reviewed_by', 'current_version', 'created_at'],
            allowCreate: false,
            allowUpdate: false,
            allowDelete: false,
            orderBy: 'id',
            orderDirection: 'desc',
            scope: static function (Builder $query): void {
                $actor = app(ExecutionContext::class)->actor;
                $user = $actor === null ? Auth::user() : app(CapabilityAuthorizer::class)->user($actor);
                if (! $user instanceof User) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                app(EditorialVisibilityInterface::class)->constrain($query, $user);
            },
        );
    }
}
