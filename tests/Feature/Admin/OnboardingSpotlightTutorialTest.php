<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('core backoffice onboarding targets rendered navigation in both brands', function (string $brand): void {
    config(['cms.admin_theme' => $brand]);
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin, 'web')
        ->get(route('admin.dashboard'));

    $response->assertStatus(200);

    $response->assertSee('start-onboarding-tour');
    $response->assertSee("target: '#nav-pages'", false);

    $html = (string) $response->getContent();
    preg_match_all("/target:\\s*'(#nav-[^']+)'/", $html, $targets);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    foreach ($targets[1] as $target) {
        expect($document->getElementById(substr($target, 1)))->not->toBeNull("Missing onboarding target: {$target}");
    }
})->with(['dbcms', 'termocms']);
