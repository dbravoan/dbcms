<?php

declare(strict_types=1);

namespace Modules\Captcha;

use App\Core\Forms\FieldTypeRegistry;
use App\Core\Modules\BaseModuleServiceProvider;
use Illuminate\Support\Facades\Validator;
use Modules\Captcha\Fields\CaptchaFieldType;
use Modules\Captcha\Services\CaptchaService;

class CaptchaModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'captcha';

    protected string $name = 'Security & Dynamic Captcha';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(CaptchaService::class);
        $this->app->tag(CaptchaService::class, 'cms.csp.connect');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'captcha');

        // Register the `captcha` form field type in the core form framework.
        if ($this->app->bound(FieldTypeRegistry::class)) {
            $this->app->make(FieldTypeRegistry::class)->register(new CaptchaFieldType);
        }

        // Custom validation rule 'captcha'
        Validator::extend('captcha', function ($attribute, $value, $parameters, $validator): bool {
            /** @var CaptchaService $service */
            $service = app(CaptchaService::class);

            return $service->verifyRequest(request()->all(), request()->ip());
        }, 'El código de seguridad Captcha no es correcto o ha expirado.');
    }

    public function getAdminNavigation(): array
    {
        return [];
    }
}
