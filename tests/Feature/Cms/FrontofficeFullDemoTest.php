<?php

declare(strict_types=1);

use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DemoComposition;

uses(RefreshDatabase::class, DemoComposition::class);

beforeEach(function (): void {
    config(['cms.name' => 'dbCMS', 'cms.theme' => 'db-cyber']);
    $this->seed(PlatformShowcaseSeeder::class);
});

test('frontoffice root redirects to default market and locale', function (): void {
    $response = $this->get('/');

    $response->assertRedirect('/eu/es/');
});

test('frontoffice home page renders with dbCMS branding and cyber aesthetic', function (): void {
    $response = $this->get('/eu/es/');

    $response->assertStatus(200);
    $response->assertSee('dbCMS');
    $response->assertSee('polygon-canvas');
});

test('frontoffice blog hub renders technical articles', function (): void {
    $response = $this->get('/eu/es/blog');

    $response->assertStatus(200);
    $response->assertSee('Artículos', false);
    $response->assertSee('Arquitectura limpia');
});

test('frontoffice llms.txt provides generative engine markdown schema', function (): void {
    $response = $this->get('/llms.txt');

    $response->assertStatus(200);
    $response->assertSee('# dbCMS');
});
