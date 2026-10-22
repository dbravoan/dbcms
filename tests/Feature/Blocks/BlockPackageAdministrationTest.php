<?php

declare(strict_types=1);

use App\Core\Blocks\BlockPackageManager;
use App\Models\User;
use Tests\Support\CmsFixtures;

test('block package administration is available in either backoffice theme', function (string $theme): void {
    config(['cms.admin_theme' => $theme]);
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->actingAs(User::where('email', 'admin@db-cms.io')->firstOrFail());
    $this->get(route('admin.block-packages.index'))->assertOk()->assertSee('Bloques básicos')->assertSee('Charts')->assertSee('Archivo ZIP');
    $this->post(route('admin.block-packages.toggle', 'basic'), ['enabled' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect(app(BlockPackageManager::class)->isEnabled('basic'))->toBeFalse();
})->with(['dbcms', 'termocms']);

test('users without block management permission cannot install or change packages', function (): void {
    CmsFixtures::identities(['editor' => ['access:admin']]);
    $this->actingAs(User::where('email', 'editor@db-cms.io')->firstOrFail());
    $this->get(route('admin.block-packages.index'))->assertForbidden();
    $this->post(route('admin.block-packages.toggle', 'basic'), ['enabled' => false])->assertForbidden();
    $this->post(route('admin.block-packages.upload'))->assertForbidden();
});
