<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Console;

use Illuminate\Console\Command;
use Modules\CreativeStudio\Services\CreativeStudioDomainService;

final class RecoverCreativeTasksCommand extends Command
{
    protected $signature = 'creative-studio:recover';

    protected $description = 'Detecta generaciones interrumpidas sin repetir solicitudes de pago';

    public function handle(CreativeStudioDomainService $service): int
    {
        $this->info((string) $service->recoverExpired().' tareas pendientes de comprobación.');

        return self::SUCCESS;
    }
}
