<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\CmsCapabilitiesServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FilterServiceProvider;
use App\Providers\FormFieldTypesServiceProvider;
use App\Providers\PassportServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    CmsCapabilitiesServiceProvider::class,
    FilterServiceProvider::class,
    FormFieldTypesServiceProvider::class,
    AuthServiceProvider::class,
    DomainServiceProvider::class,
    PassportServiceProvider::class,
    SearchServiceProvider::class,
    SeoServiceProvider::class,
];
