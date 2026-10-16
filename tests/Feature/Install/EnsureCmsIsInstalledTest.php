<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('uninstalled system redirects root web visits to /install', function (): void {
    if (File::exists(storage_path('installed'))) {
        File::delete(storage_path('installed'));
    }

    $response = $this->get('/');

    $response->assertRedirect(route('install.step1'));
});

test('installer routes bypass EnsureCmsIsInstalled redirection when uninstalled', function (): void {
    if (File::exists(storage_path('installed'))) {
        File::delete(storage_path('installed'));
    }

    $response1 = $this->get(route('install.step1'));
    $response1->assertStatus(200);
    $response1->assertSee('¿Qué es dbCMS');

    $response2 = $this->get(route('install.step2'));
    $response2->assertStatus(200);
    $response2->assertSee('Configuración de la Base de Datos');
});

test('installed system redirects installer visits to admin login', function (): void {
    File::put(storage_path('installed'), (string) json_encode([
        'installed_at' => now()->toIso8601String(),
        'admin_path' => 'admin',
    ]));

    $response = $this->get(route('install.step1'));

    $response->assertRedirect('/admin/login');
});
