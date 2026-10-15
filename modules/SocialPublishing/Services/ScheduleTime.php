<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ScheduleTime
{
    public function parse(string $at): Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})$/D', $at)) {
            throw ValidationException::withMessages(['publish_at' => 'Indica fecha ISO con Z o desplazamiento horario explícito.']);
        }
        try {
            $parsed = Carbon::parse($at);
            $utc = $parsed->copy()->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['publish_at' => 'Fecha no válida.']);
        }
        if ($parsed->format('Y-m-d\TH:i') !== substr($at, 0, 16)) {
            throw ValidationException::withMessages(['publish_at' => 'La fecha indicada no existe.']);
        }
        if (! str_ends_with($at, 'Z')) {
            $local = $utc->copy()->timezone((string) config('social_publishing.timezone', config('app.timezone', 'UTC')));
            if ($local->format('Y-m-d\TH:iP') !== $parsed->format('Y-m-d\TH:iP')) {
                throw ValidationException::withMessages(['publish_at' => 'Esa hora o desplazamiento no existe en la zona de esta instalación. Para la hora repetida elige el desplazamiento explícito.']);
            }
        }

        return $utc;
    }
}
