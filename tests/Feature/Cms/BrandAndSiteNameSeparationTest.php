<?php

declare(strict_types=1);

use App\Contracts\Services\BrandServiceInterface;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('the shared brand name is the brand identity, kept apart from the site name', function (): void {
    config(['cms.name' => 'SiteSentinel']);

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin, 'web')->get(route('admin.dashboard'));

    $response->assertOk();

    $brandName = app(BrandServiceInterface::class)->current()->name;

    expect($response->viewData('cmsSiteName'))->toBe('SiteSentinel')
        ->and($response->viewData('cmsBrandName'))->toBe($brandName)
        ->and($response->viewData('cmsBrandName'))->not->toBe('SiteSentinel');
});
