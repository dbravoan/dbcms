<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Enums;

enum AuditStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHED = 'published';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::PENDING_REVIEW => 'Pendiente de Revisión',
            self::APPROVED => 'Aprobado',
            self::REJECTED => 'Rechazado (Requiere Cambios)',
            self::PUBLISHED => 'Publicado',
            self::DELETED => 'Eliminado',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-800 text-gray-400 border border-gray-700',
            self::PENDING_REVIEW => 'bg-amber-500/15 text-amber-400 border border-amber-500/40 animate-pulse',
            self::APPROVED => 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/40',
            self::REJECTED => 'bg-red-500/15 text-red-400 border border-red-500/40',
            self::PUBLISHED => 'bg-[#00FF99]/15 text-[#00FF99] border border-[#00FF99]/40',
            self::DELETED => 'bg-red-500/15 text-red-400 border border-red-500/40',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'fa-pencil',
            self::PENDING_REVIEW => 'fa-clock-rotate-left',
            self::APPROVED => 'fa-circle-check',
            self::REJECTED => 'fa-circle-xmark',
            self::PUBLISHED => 'fa-globe',
            self::DELETED => 'fa-trash',
        };
    }
}
