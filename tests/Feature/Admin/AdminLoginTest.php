<?php

declare(strict_types=1);

use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('a guest can switch the backoffice language from the login screen', function (): void {
    $this->from('/admin/login')
        ->post('/admin/locale', ['locale' => 'en'])
        ->assertRedirect('/admin/login');

    expect(session('backoffice_locale'))->toBe('en');

    // The login screen itself is now rendered in the chosen language.
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('Panel access');

    $this->post('/admin/locale', ['locale' => 'es'])->assertRedirect();

    expect(session('backoffice_locale'))->toBe('es');
});

test('the locale switcher is a working segmented control that marks the current locale', function (): void {
    $response = $this->get('/admin/login')->assertOk();

    $response->assertSee('action="'.route('admin.locale.update').'"', false)
        ->assertSee('name="locale"', false)
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('value="en"', false);
});

test('the termocms login renders the redesigned interface', function (): void {
    $html = view('themes.admin.termocms.admin.auth.login', ['cspNonce' => 'test-nonce'])->render();

    expect($html)
        // Heading + subtitle, single brand block (no duplicated brand copy).
        ->toContain('id="login-heading"')
        ->toContain(__('admin.auth.login_subtitle'))
        // Accessible, autofilled fields with a password toggle.
        ->toContain('autocomplete="username"')
        ->toContain('autocomplete="current-password"')
        ->toContain('data-password-toggle="login-password"')
        // Non-JS language switcher (light variant) and the collapsed demo panel.
        ->toContain('aria-pressed')
        ->toContain('<details')
        ->toContain('fa-envelope');
});
