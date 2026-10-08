<?php

declare(strict_types=1);

namespace Modules\Meta;

use App\Core\Modules\BaseModuleServiceProvider;
use Modules\Meta\Services\MetaCapiService;

class MetaModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'meta';

    protected string $name = 'Meta Pixel & Server CAPI';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(MetaCapiService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'meta');
    }
}
