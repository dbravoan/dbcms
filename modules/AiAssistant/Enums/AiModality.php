<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Enums;

enum AiModality: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Texto y Razonamiento',
            self::IMAGE => 'Generación de Imágenes',
            self::VIDEO => 'Vídeo Cinemático',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TEXT => 'fa-solid fa-align-left',
            self::IMAGE => 'fa-solid fa-image',
            self::VIDEO => 'fa-solid fa-video',
        };
    }
}
