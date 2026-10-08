<?php

declare(strict_types=1);

use App\Models\CmsModule;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Captcha\CaptchaModuleServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

function enableCaptchaForBuilder(bool $active): void
{
    CmsModule::updateOrCreate(
        ['identifier' => 'captcha'],
        [
            'name' => 'Security & Dynamic Captcha',
            'version' => '1.0.0',
            'description' => 'test',
            'provider' => CaptchaModuleServiceProvider::class,
            'is_pageable' => false,
            'is_installed' => true,
            'is_active' => $active,
            'settings' => null,
        ],
    );
}

test('the form builder offers the captcha field type while the module is active', function (): void {
    enableCaptchaForBuilder(true);

    $response = $this->actingAs($this->admin, 'web')->get('/admin/forms/create');

    $response->assertOk();
    $response->assertSee('&quot;key&quot;:&quot;captcha&quot;', false);
});

test('the form builder hides the captcha field type when the module is disabled', function (): void {
    enableCaptchaForBuilder(false);

    $response = $this->actingAs($this->admin, 'web')->get('/admin/forms/create');

    $response->assertOk();
    $response->assertDontSee('&quot;key&quot;:&quot;captcha&quot;', false);
});
