<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;
use Tests\Support\FormsCaptchaComposition;

uses(RefreshDatabase::class, FormsCaptchaComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('the form builder offers the captcha field type while the module is active', function (): void {

    $response = $this->actingAs($this->admin, 'web')->get('/admin/forms/create');

    $response->assertOk();
    $response->assertSee('&quot;key&quot;:&quot;captcha&quot;', false);
});

test('the form builder hides the captcha field type when the module is disabled', function (): void {
    app(ModuleManager::class)->deactivate('captcha');

    $response = $this->actingAs($this->admin, 'web')->get('/admin/forms/create');

    $response->assertOk();
    $response->assertDontSee('&quot;key&quot;:&quot;captcha&quot;', false);
});
