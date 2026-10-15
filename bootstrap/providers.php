<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\CmsCapabilitiesServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FilterServiceProvider;
use App\Providers\FormFieldTypesServiceProvider;
use App\Providers\IntegrationGuidesServiceProvider;
use App\Providers\MarketingServiceProvider;
use App\Providers\PassportServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SeoServiceProvider;
use App\Providers\UpdateServiceProvider;

return [
    AppServiceProvider::class,
    CmsCapabilitiesServiceProvider::class,
    IntegrationGuidesServiceProvider::class,
    MarketingServiceProvider::class,
    FilterServiceProvider::class,
    FormFieldTypesServiceProvider::class,
    AuthServiceProvider::class,
    DomainServiceProvider::class,
    PassportServiceProvider::class,
    SearchServiceProvider::class,
    SeoServiceProvider::class,
    UpdateServiceProvider::class,
];
