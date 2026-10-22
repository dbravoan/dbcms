<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Enums;

enum AuditAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHED = 'published';
    case UNPUBLISHED = 'unpublished';
    case ROLLBACK = 'rollback';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Creación Inicial',
            self::UPDATED => 'Modificación de Contenido',
            self::SUBMITTED => 'Envío a Revisión de Calidad',
            self::APPROVED => 'Aprobación Editorial',
            self::REJECTED => 'Rechazo con Observaciones',
            self::PUBLISHED => 'Publicación Directa en Vivo',
            self::UNPUBLISHED => 'Despublicación de Contenido',
            self::ROLLBACK => 'Reversión a Versión Anterior',
            self::DELETED => 'Eliminación de Contenido',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'fa-plus',
            self::UPDATED => 'fa-pen-to-square',
            self::SUBMITTED => 'fa-paper-plane',
            self::APPROVED => 'fa-check-double',
            self::REJECTED => 'fa-ban',
            self::PUBLISHED => 'fa-upload',
            self::UNPUBLISHED => 'fa-eye-slash',
            self::ROLLBACK => 'fa-rotate-left',
            self::DELETED => 'fa-trash',
        };
    }
}
