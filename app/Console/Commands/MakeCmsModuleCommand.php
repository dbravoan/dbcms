<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Modules\ModuleManifestGraph;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class MakeCmsModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:cms-module 
                            {name : The module name (e.g. Events, Portfolio, Testimonials)} 
                            {--pageable : Whether the module serves its own frontoffice routes}
                            {--with-auth : Whether the module provides its own isolated authenticatable users}
                            {--dedicated-panel : Whether the module generates a dedicated administrative portal}
                            {--crud : Scaffold index + create/edit form views using the reusable Admin UI Kit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scaffolds a complete, decoupled CMS module adhering to Clean Architecture and multi-market standards';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawName = (string) $this->argument('name');
        $studlyName = Str::studly($rawName);
        $identifier = Str::slug(Str::snake($rawName));
        $isPageable = (bool) $this->option('pageable');
        $withAuth = (bool) $this->option('with-auth') || (bool) $this->option('dedicated-panel');
        $dedicatedPanel = (bool) $this->option('dedicated-panel');
        $crud = (bool) $this->option('crud');

        if (! preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/D', $studlyName)) {
            $this->error('Use a module name that produces a valid PascalCase directory.');

            return self::FAILURE;
        }
        try {
            ModuleManifestGraph::validateManifest([
                'identifier' => $identifier, 'name' => Str::headline($rawName), 'version' => '1.0.0',
                'provider' => "Modules\\{$studlyName}\\{$studlyName}ModuleServiceProvider",
            ]);
        } catch (\DomainException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $basePath = base_path("modules/{$studlyName}");

        if (File::exists($basePath)) {
            $this->error("El módulo [{$studlyName}] ya existe en modules/{$studlyName}.");

            return self::FAILURE;
        }

        $this->info("Generando módulo CMS [{$studlyName}] con identificador '{$identifier}'...");

        // 1. Create directory structure
        $directories = [
            $basePath.'/Contracts/Services',
            $basePath.'/Database/Migrations',
            $basePath.'/DTOs',
            $basePath.'/Http/Controllers/Admin',
            $basePath.'/Http/Controllers/Admin/Concerns',
            $basePath.'/Models',
            $basePath.'/Resources/views/admin',
            $basePath.'/Routes',
            $basePath.'/Services',
        ];

        if ($isPageable) {
            $directories[] = $basePath.'/Http/Controllers';
            $directories[] = $basePath.'/Resources/views';
        }

        if ($withAuth) {
            $directories[] = $basePath.'/Http/Controllers/Portal';
            $directories[] = $basePath.'/Resources/views/portal';
        }

        foreach ($directories as $dir) {
            File::makeDirectory($dir, 0755, true, true);
        }

        // 2. Generate module.json
        $manifest = [
            'identifier' => $identifier,
            'name' => Str::headline($rawName),
            'version' => '1.0.0',
            'description' => "Módulo {$studlyName} desacoplado para dbCMS",
            'provider' => "Modules\\{$studlyName}\\{$studlyName}ModuleServiceProvider",
            'is_pageable' => $isPageable,
            'has_dedicated_panel' => $dedicatedPanel,
            'requires' => ['core' => '^'.(string) config('cms.version')],
            'permissions' => [
                "manage:{$identifier}",
            ],
            'settings' => (object) [],
        ];
        File::put($basePath.'/module.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // 3. Service Provider
        File::put($basePath."/{$studlyName}ModuleServiceProvider.php", $this->getServiceProviderContent($studlyName, $identifier, $isPageable, $withAuth, $dedicatedPanel));

        // 4. Models
        File::put($basePath."/Models/{$studlyName}.php", $this->getModelContent($studlyName, $identifier));
        File::put($basePath."/Models/{$studlyName}Translation.php", $this->getTranslationModelContent($studlyName, $identifier));
        if ($withAuth) {
            File::put($basePath."/Models/{$studlyName}User.php", $this->getUserModelContent($studlyName, $identifier));
        }

        // 5. Contract & Domain Service
        File::put($basePath."/Contracts/Services/{$studlyName}ServiceInterface.php", $this->getContractContent($studlyName));
        File::put($basePath."/Services/{$studlyName}DomainService.php", $this->getServiceContent($studlyName));

        // 6. Controllers (thin, invokable verb controllers)
        File::put($basePath."/Http/Controllers/Admin/{$studlyName}GetController.php", $this->getAdminIndexControllerContent($studlyName, $identifier));
        if ($crud) {
            File::put($basePath."/Http/Controllers/Admin/Concerns/Validates{$studlyName}Payload.php", $this->getValidationTraitContent($studlyName));
            File::put($basePath."/Http/Controllers/Admin/{$studlyName}CreateGetController.php", $this->getAdminCreateControllerContent($studlyName, $identifier));
            File::put($basePath."/Http/Controllers/Admin/{$studlyName}PostController.php", $this->getAdminStoreControllerContent($studlyName, $identifier));
            File::put($basePath."/Http/Controllers/Admin/{$studlyName}EditGetController.php", $this->getAdminEditControllerContent($studlyName, $identifier));
            File::put($basePath."/Http/Controllers/Admin/{$studlyName}PutController.php", $this->getAdminUpdateControllerContent($studlyName, $identifier));
            File::put($basePath."/Http/Controllers/Admin/{$studlyName}DeleteController.php", $this->getAdminDeleteControllerContent($studlyName, $identifier));
        }
        File::put($basePath.'/Routes/admin.php', $this->getAdminRoutesContent($studlyName, $identifier, $crud));
        if ($isPageable) {
            File::put($basePath."/Http/Controllers/{$studlyName}FrontController.php", $this->getFrontControllerContent($studlyName, $identifier));
        }
        if ($dedicatedPanel) {
            File::put($basePath."/Http/Controllers/Portal/{$studlyName}PortalController.php", $this->getPortalControllerContent($studlyName, $identifier));
        }

        // 7. Migrations
        $timestamp = date('Y_m_d_His');
        $snakeName = Str::snake($studlyName);
        File::put($basePath."/Database/Migrations/{$timestamp}_create_{$snakeName}_tables.php", $this->getMigrationContent($studlyName, $identifier));
        if ($withAuth) {
            $userTimestamp = date('Y_m_d_His', time() + 1);
            File::put($basePath."/Database/Migrations/{$userTimestamp}_create_{$identifier}_users_table.php", $this->getUserMigrationContent($studlyName, $identifier));
        }

        // 8. Views
        File::put($basePath.'/Resources/views/admin/index.blade.php', $this->getAdminIndexViewContent($studlyName, $identifier, $crud));
        if ($crud) {
            File::put($basePath.'/Resources/views/admin/_form.blade.php', $this->getAdminFormPartialContent($studlyName, $identifier));
            File::put($basePath.'/Resources/views/admin/create.blade.php', $this->getAdminCreateViewContent($studlyName, $identifier));
            File::put($basePath.'/Resources/views/admin/edit.blade.php', $this->getAdminEditViewContent($studlyName, $identifier));
        }
        if ($isPageable) {
            File::put($basePath.'/Resources/views/index.blade.php', $this->getFrontIndexViewContent($studlyName, $identifier));
        }
        if ($dedicatedPanel) {
            File::put($basePath.'/Resources/views/portal/layout.blade.php', $this->getPortalLayoutContent($studlyName, $identifier));
            File::put($basePath.'/Resources/views/portal/dashboard.blade.php', $this->getPortalDashboardContent($studlyName, $identifier));
            File::put($basePath.'/Resources/views/portal/login.blade.php', $this->getPortalLoginContent($studlyName, $identifier));
        }

        $this->info("¡Módulo [{$studlyName}] creado exitosamente en modules/{$studlyName}!");
        $this->line('Instálalo desde el gestor de módulos o inclúyelo en CMS_MODULES antes de instalar el proyecto.');

        return self::SUCCESS;
    }

    /**
     * Render a stub file, replacing its `__TOKEN__` placeholders.
     *
     * @param  array<string, string>  $replacements
     */
    protected function stub(string $name, array $replacements = []): string
    {
        $path = base_path("stubs/cms-module/{$name}.stub");

        if (! File::exists($path)) {
            throw new RuntimeException("Stub [{$name}] not found at {$path}.");
        }

        return str_replace(array_keys($replacements), array_values($replacements), (string) File::get($path));
    }

    /**
     * Common placeholder map for a module.
     *
     * @return array<string, string>
     */
    protected function tokens(string $studly, string $identifier): array
    {
        $names = $this->moduleNames($studly);

        return [
            '__STUDLY__' => $studly,
            '__IDENTIFIER__' => $identifier,
            '__HEADLINE__' => Str::headline($studly),
            '__SNAKE__' => $names['snake'],
            '__TABLE__' => $names['plural'],
            '__PIVOT_TABLE__' => $names['pivotTable'],
            '__FK__' => $names['fk'],
            '__TRANSLATION_CLASS__' => "{$studly}Translation",
        ];
    }

    /**
     * Resolve the canonical database identifiers for a module.
     *
     * `identifier` (hyphenated slug) is only used for routes, permissions and
     * view namespaces; database objects must use underscored snake_case.
     *
     * @return array{snake: string, plural: string, translationTable: string, pivotTable: string, fk: string}
     */
    protected function moduleNames(string $studly): array
    {
        $snake = Str::snake($studly);

        return [
            'snake' => $snake,
            'plural' => Str::snake(Str::pluralStudly($studly)),
            'translationTable' => $snake.'_translations',
            'pivotTable' => 'market_'.$snake,
            'fk' => $snake.'_id',
        ];
    }

    protected function getServiceProviderContent(string $studly, string $identifier, bool $isPageable, bool $withAuth = false, bool $dedicatedPanel = false): string
    {
        $tokens = $this->tokens($studly, $identifier);
        $tokens['__PAGEABLE_FLAG__'] = $isPageable ? 'true' : 'false';

        $authBoot = '';
        if ($withAuth) {
            $authBoot .= $this->stub('fragments/auth_register', $tokens);
        }
        if ($dedicatedPanel) {
            $authBoot .= $this->stub('fragments/portal_routes', $tokens);
        }
        $tokens['__AUTH_BOOT__'] = $authBoot;
        $tokens['__ROUTES_METHOD__'] = $isPageable ? $this->stub('fragments/routes_method', $tokens) : '';
        $tokens['__PANEL_METHODS__'] = $dedicatedPanel ? $this->stub('fragments/panel_methods', $tokens) : '';

        return $this->stub('service_provider', $tokens);
    }

    protected function getModelContent(string $studly, string $identifier): string
    {
        return $this->stub('model', $this->tokens($studly, $identifier));
    }

    protected function getTranslationModelContent(string $studly, string $identifier): string
    {
        return $this->stub('translation_model', $this->tokens($studly, $identifier));
    }

    protected function getUserModelContent(string $studly, string $identifier): string
    {
        return $this->stub('user_model', $this->tokens($studly, $identifier));
    }

    protected function getContractContent(string $studly): string
    {
        return $this->stub('contract', $this->tokens($studly, $studly));
    }

    protected function getServiceContent(string $studly): string
    {
        return $this->stub('domain_service', $this->tokens($studly, $studly));
    }

    protected function getAdminIndexControllerContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_index_controller', $this->tokens($studly, $identifier));
    }

    protected function getValidationTraitContent(string $studly): string
    {
        return $this->stub('validation_trait', $this->tokens($studly, $studly));
    }

    protected function getAdminCreateControllerContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_create_controller', $this->tokens($studly, $identifier));
    }

    protected function getAdminStoreControllerContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_store_controller', $this->tokens($studly, $identifier));
    }

    protected function getAdminEditControllerContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_edit_controller', $this->tokens($studly, $identifier));
    }

    protected function getAdminUpdateControllerContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_update_controller', $this->tokens($studly, $identifier));
    }

    protected function getAdminDeleteControllerContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_delete_controller', $this->tokens($studly, $identifier));
    }

    protected function getAdminRoutesContent(string $studly, string $identifier, bool $crud): string
    {
        $tokens = $this->tokens($studly, $identifier);
        $tokens['__CRUD_IMPORTS__'] = $crud ? $this->stub('fragments/crud_imports', $tokens) : '';
        $tokens['__CRUD_ROUTES__'] = $crud ? $this->stub('fragments/crud_routes', $tokens) : '';

        return $this->stub('admin_routes', $tokens);
    }

    protected function getFrontControllerContent(string $studly, string $identifier): string
    {
        return $this->stub('front_controller', $this->tokens($studly, $identifier));
    }

    protected function getPortalControllerContent(string $studly, string $identifier): string
    {
        return $this->stub('portal_controller', $this->tokens($studly, $identifier));
    }

    protected function getMigrationContent(string $studly, string $identifier): string
    {
        return $this->stub('migration', $this->tokens($studly, $identifier));
    }

    protected function getUserMigrationContent(string $studly, string $identifier): string
    {
        return $this->stub('user_migration', $this->tokens($studly, $identifier));
    }

    protected function getAdminIndexViewContent(string $studly, string $identifier, bool $crud = false): string
    {
        $tokens = $this->tokens($studly, $identifier);
        $tokens['__HEADER_CREATE__'] = $crud ? $this->stub('fragments/header_create', $tokens) : '';
        $tokens['__EMPTY_ACTION__'] = $crud ? $this->stub('fragments/empty_action', $tokens) : '';
        $tokens['__ROW_ACTIONS__'] = $crud ? $this->stub('fragments/row_actions', $tokens) : '<span class="text-gray-500">—</span>';

        return $this->stub('admin_index_view', $tokens);
    }

    protected function getAdminFormPartialContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_form_view', $this->tokens($studly, $identifier));
    }

    protected function getAdminCreateViewContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_create_view', $this->tokens($studly, $identifier));
    }

    protected function getAdminEditViewContent(string $studly, string $identifier): string
    {
        return $this->stub('admin_edit_view', $this->tokens($studly, $identifier));
    }

    protected function getFrontIndexViewContent(string $studly, string $identifier): string
    {
        return $this->stub('front_index_view', $this->tokens($studly, $identifier));
    }

    protected function getPortalLayoutContent(string $studly, string $identifier): string
    {
        return $this->stub('portal_layout', $this->tokens($studly, $identifier));
    }

    protected function getPortalDashboardContent(string $studly, string $identifier): string
    {
        return $this->stub('portal_dashboard', $this->tokens($studly, $identifier));
    }

    protected function getPortalLoginContent(string $studly, string $identifier): string
    {
        return $this->stub('portal_login', $this->tokens($studly, $identifier));
    }
}
