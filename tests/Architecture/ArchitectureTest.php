<?php

declare(strict_types=1);

test('all PHP files in app directory enforce strict types', function (): void {
    expect('App')->toUseStrictTypes();
});

test('controllers depend only on declared application and framework layers', function (): void {
    expect('App\Http\Controllers')
        ->toOnlyUse([
            'App\Contracts\Services',
            'App\DTOs',
            'App\Services',
            'App\Core',
            'App\Enums',
            'App\Http\Controllers',
            'App\Http\Requests',
            'App\Http\Resources',
            'App\Http\Adapters',
            'App\Models',

            'Modules',
            'Database\Seeders',
            'Illuminate',
            'Laravel',
            'Laravel\Passport',
            'Symfony',

            'Symfony\Component\HttpFoundation',

            'PDO',
            '__',
            'view',
            'back',
            'request',
            'response',
            'redirect',
            'url',
            'route',
            'bcrypt',
            'public_path',
            'storage_path',
            'base_path',
            'auth',
            'config',
            'env',
            'now',
            'collect',
            'abort',
            'abort_if',
            'abort_unless',
            'class_basename',
        ]);

});

test('all DTOs are readonly classes', function (): void {
    expect('App\DTOs')
        ->toBeReadonly();
});

test('domain services implement their corresponding contract interface', function (): void {
    expect('App\Services\Auth\UserAuthService')
        ->toImplement('App\Contracts\Services\UserAuthServiceInterface');

    expect('App\Services\Geo\LlmsTxtGeneratorService')
        ->toImplement('App\Contracts\Services\GeoServiceInterface');

    expect('App\Services\Seo\SitemapService')
        ->toImplement('App\Contracts\Services\SeoServiceInterface');
});

test('models do not execute external SDK or curl calls', function (): void {
    expect('App\Models')
        ->not->toUse(['Illuminate\Support\Facades\Http', 'GuzzleHttp']);
});

test('domain services are HTTP-agnostic (no dependency on the HTTP layer)', function (): void {
    expect('App\Services')
        ->not->toUse(['App\Http']);
});

test('the reusable core stays out of the HTTP controller/request layer', function (): void {
    expect('App\Core')
        ->not->toUse(['App\Http\Controllers', 'App\Http\Requests']);
});
