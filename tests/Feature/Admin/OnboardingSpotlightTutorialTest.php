<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('backoffice dashboard includes onboarding tutorial with spotlight highlighting elements and DOM targets', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin, 'web')
        ->get(route('admin.dashboard'));

    $response->assertStatus(200);

    // Verify tutorial component is rendered
    $response->assertSee('start-onboarding-tour');
    $response->assertSee('spotlight');
    $response->assertSee('recalculateSpotlight');

    // Verify all DOM targets exist in the layout
    $response->assertSee('id="nav-persona-switcher"', false);
    $response->assertSee('id="nav-modules"', false);
    $response->assertSee('id="nav-pages"', false);
    $response->assertSee('id="nav-blog"', false);
    $response->assertSee('id="nav-forms"', false);
    $response->assertSee('id="nav-adminer"', false);

    // Verify spotlight effects and cyber styling
    $response->assertSee('animate-ping');
    $response->assertSee('box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.82)');
    $response->assertSee('#00FF99');
});
