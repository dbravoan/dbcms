<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Projects\AiLearningStarterService;
use Illuminate\Console\Command;

final class AiLearningStarterCommand extends Command
{
    protected $signature = 'cms:starter:ai-learning {--author= : Correo del administrador que firma los artículos} {--market=es : Mercado activo con idioma español}';

    protected $description = 'Instala el proyecto editorial opcional IA en práctica en un CMS vacío, sin sobrescribir contenido';

    public function handle(AiLearningStarterService $starter): int
    {
        try {
            $created = $starter->install((string) $this->option('author'), (string) $this->option('market'));
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info($created ? 'IA en práctica instalado: páginas, guías, navegación y tema listos para editar.' : 'El starter ya se instaló. Se han conservado todos los cambios editoriales.');

        return self::SUCCESS;
    }
}
