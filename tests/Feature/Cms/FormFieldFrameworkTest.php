<?php

declare(strict_types=1);

use App\Core\Forms\SchemaFactory;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
    view()->share('errors', new ViewErrorBag);
});

test('the shared renderer renders a database-backed form (seeded contact page)', function (): void {
    $response = $this->get('/es/es/contacto');

    $response->assertOk()
        ->assertSee('name="nombre"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="empresa"', false)
        ->assertSee('name="mensaje"', false)
        ->assertSee('_hp_check', false);
});

test('the same renderer renders an inline array schema defined in blade', function (): void {
    $html = Blade::render(
        '<x-cms-form.fields :schema="$schema" variant="cyber" honeypot="_hp_check" />',
        ['schema' => (new SchemaFactory)->fromArray([
            ['field_key' => 'apodo', 'type' => 'text', 'label' => 'Apodo', 'is_required' => true, 'placeholder' => 'Tu apodo'],
            ['field_key' => 'plan', 'type' => 'select', 'label' => 'Plan', 'options' => "pro | Pro\nlite | Lite"],
            ['field_key' => 'bio', 'type' => 'textarea', 'label' => 'Biografía'],
        ])],
    );

    expect($html)
        ->toContain('name="apodo"')
        ->toContain('placeholder="Tu apodo"')
        ->toContain('name="plan"')
        ->toContain('value="pro"')
        ->toContain('name="bio"')
        ->toContain('_hp_check');
});

test('the same renderer renders a json schema', function (): void {
    $json = (string) json_encode([
        ['field_key' => 'ciudad', 'type' => 'text', 'label' => 'Ciudad', 'is_required' => true],
    ]);

    $html = Blade::render(
        '<x-cms-form.fields :schema="$schema" variant="light" />',
        ['schema' => (new SchemaFactory)->fromJson($json)],
    );

    expect($html)->toContain('name="ciudad"')->toContain('text-slate-700');
});

test('unavailable field types render nothing and are not validated', function (): void {
    // `captcha` is declared in config/forms.php but the Captcha module registers
    // no type in this scenario, so the field must be ignored entirely.
    $html = Blade::render(
        '<x-cms-form.fields :schema="$schema" variant="cyber" />',
        ['schema' => (new SchemaFactory)->fromArray([
            ['field_key' => 'nombre', 'type' => 'text', 'label' => 'Nombre'],
            ['field_key' => 'captcha', 'type' => 'captcha', 'label' => 'Captcha'],
        ])],
    );

    expect($html)->toContain('name="nombre"')
        ->and($html)->not->toContain('name="captcha"');
});
