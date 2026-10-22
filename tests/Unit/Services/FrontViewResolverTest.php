<?php

declare(strict_types=1);
use App\Services\Cms\FrontViewResolver;

uses()->group('source-catalogue');

test('it resolves an existing theme view', function (): void {
    config(['cms.theme' => 'db-cyber']);

    $resolver = app(FrontViewResolver::class);

    expect($resolver->theme())->toBe('db-cyber')
        ->and($resolver->resolve('blog.index', 'blog::index'))->toBe('themes::db-cyber.blog.index');
});

test('it falls back to the module view when the theme does not override it', function (): void {
    config(['cms.theme' => 'db-cyber']);

    expect(app(FrontViewResolver::class)->resolve('blog.missing', 'blog::index'))->toBe('blog::index');
});

test('it falls back to the default theme when the configured one is unknown', function (): void {
    config(['cms.theme' => 'does-not-exist']);

    expect(app(FrontViewResolver::class)->layout())->toBe('themes::db-cyber.layouts.app');
});

test('it resolves module block views through the registered theme namespace', function (): void {
    config(['cms.theme' => 'db-cyber']);

    expect(app(FrontViewResolver::class)->resolve('shop.blocks.products', 'shop::blocks.products'))
        ->toBe('themes::db-cyber.shop.blocks.products');
});

test('it returns null for a block view that exists nowhere', function (): void {
    config(['cms.theme' => 'db-cyber']);

    expect(app(FrontViewResolver::class)->block('blocks.definitely-missing'))->toBeNull();
});
