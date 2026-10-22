<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\DirectoryFixture;

/** @param list<string> $excluded */
function withInstallerBrandsHidden(array $excluded, Closure $callback): void
{
    if ($excluded === []) {
        $callback();

        return;
    }
    $brand = array_shift($excluded);
    DirectoryFixture::hide(
        resource_path('views/themes/admin/'.$brand),
        resource_path('views/themes/admin/.'.$brand.'-installer-hidden'),
        fn () => withInstallerBrandsHidden($excluded, $callback),
    );
}

beforeEach(function (): void {
    $this->physicalBrands = array_map('basename', File::directories(resource_path('views/themes/admin')));
    $this->physicalThemes = array_map('basename', File::directories(base_path('themes')));
    sort($this->physicalBrands);
    sort($this->physicalThemes);
    expect($this->physicalBrands)->not->toBeEmpty();
    expect($this->physicalThemes)->not->toBeEmpty();
    $this->selectedBrand = in_array('termocms', $this->physicalBrands, true) ? 'termocms' : $this->physicalBrands[0];
    $this->selectedTheme = in_array('db-cyber', $this->physicalThemes, true) ? 'db-cyber' : $this->physicalThemes[0];
    $this->singleBrand = in_array(config('cms.admin_theme'), $this->physicalBrands, true) ? config('cms.admin_theme') : $this->physicalBrands[0];
    config(['cms.admin_theme' => $this->singleBrand, 'cms.theme' => $this->selectedTheme]);
});

test('installer step 1 exposes server readiness and the database setup link', function (): void {
    $response = $this->get(route('install.step1'));

    $response->assertOk()->assertViewIs('install.step1_requirements');
    $requirements = $response->viewData('requirements');
    $permissions = $response->viewData('permissions');
    expect($requirements)->toBeArray()->not->toBeEmpty();
    expect($permissions)->toBeArray()->not->toBeEmpty();
    foreach ([...array_values($requirements), ...array_values($permissions)] as $passed) {
        expect($passed)->toBeBool();
    }
    expect($response->viewData('canProceed'))->toBe(! in_array(false, [...$requirements, ...$permissions], true));
    $response->assertSee('href="'.route('install.step2').'"', false);
});

test('installer step 2 tests database connection via ajax with auto-create support', function (): void {
    $response = $this->postJson(route('install.test-db'), [
        'host' => env('DB_HOST', 'mysql'),
        'port' => (string) env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME', 'sail'),
        'password' => env('DB_PASSWORD', 'secret'),
        'auto_create' => true,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
});

test('installer step 2 saves database credentials and redirects to step 3', function (): void {
    $response = $this->post(route('install.save-db'), [
        'host' => env('DB_HOST', 'mysql'),
        'port' => (string) env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME', 'sail'),
        'password' => env('DB_PASSWORD', 'secret'),
    ]);

    $response->assertRedirect(route('install.step3'));
    $stored = Dotenv\Dotenv::parse(File::get(app()->environmentFilePath()));
    expect($stored['DB_DATABASE'])->toBe(env('DB_DATABASE'))
        ->and(DB::connection('mysql')->getDatabaseName())->toBe(env('DB_DATABASE'));
});

test('installer step 3 runs provisioning migrations and seeds cleanly', function (): void {
    $response = $this->postJson(route('install.run-migrations'), [
        'with_demo' => false,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    $response->assertJsonStructure(['success', 'message', 'logs']);
});

test('installer step 4 creates primary admin, custom admin slug and seals installation lock', function (): void {
    $response = $this->post(route('install.store-admin'), [
        'name' => 'Installer Admin',
        'email' => 'master@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'dbCMS Test Platform',
        'admin_path' => 'dbadmin',
    ]);

    $response->assertRedirect(route('install.step5'));

    expect(User::where('email', 'master@db-cms.io')->exists())->toBeTrue();
    expect(File::exists(storage_path('installed')))->toBeTrue();

    $loginUrl = url('/'.config('cms.admin_path').'/login');
    $this->get(route('install.step5'))
        ->assertOk()
        ->assertViewIs('install.step5_complete')
        ->assertViewHas('loginUrl', $loginUrl)
        ->assertSee('href="'.$loginUrl.'"', false);
});

test('installer step 4 exposes the brand and front theme selectors', function (): void {
    $response = $this->get(route('install.step4'));

    $response->assertStatus(200);
    $response->assertSee('Marca del CMS', false);
    $response->assertSee('name="brand"', false);
    $response->assertSee('name="front_theme"', false);
    $response->assertSee('name="app_url"', false);
    expect(array_column($response->viewData('installBrands'), 'identifier'))->toBe($this->physicalBrands);
    expect(array_column($response->viewData('frontThemes'), 'identifier'))->toBe($this->physicalThemes);
    foreach ([...$this->physicalBrands, ...$this->physicalThemes] as $identifier) {
        $response->assertSee('value="'.$identifier.'"', false);
    }
});

test('installer auto-selects a single brand without showing a selector', function (): void {
    withInstallerBrandsHidden(array_values(array_diff($this->physicalBrands, [$this->singleBrand])), function (): void {
        $response = $this->get(route('install.step4'));

        $response->assertStatus(200);
        // No radio selector, but the single brand is still submitted.
        $response->assertDontSee('type="radio" name="brand"', false);
        $response->assertSee('type="hidden" name="brand" value="'.$this->singleBrand.'"', false);
        $response->assertSee('Única marca', false);
    });
});

test('installer accepts submission without a brand when only one is available', function (): void {
    withInstallerBrandsHidden(array_values(array_diff($this->physicalBrands, [$this->singleBrand])), function (): void {
        $response = $this->post(route('install.store-admin'), [
            'name' => 'Single Brand Admin',
            'email' => 'single-admin@db-cms.io',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'site_name' => 'Single Brand',
            'admin_path' => 'panel',
        ]);

        $response->assertRedirect(route('install.step5'));

        $lock = json_decode((string) File::get(storage_path('installed')), true);
        expect($lock['brand'])->toBe($this->singleBrand);
    });
});

test('installer persists the selected brand and front theme in the install lock', function (): void {
    $response = $this->post(route('install.store-admin'), [
        'name' => 'Brand Admin',
        'email' => 'brand-admin@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'Corporate Platform',
        'admin_path' => 'panel',
        'brand' => $this->selectedBrand,
        'front_theme' => $this->selectedTheme,
    ]);

    $response->assertRedirect(route('install.step5'));
    $response->assertSessionHas('install.brand', $this->selectedBrand);

    $lock = json_decode((string) File::get(storage_path('installed')), true);
    expect($lock['brand'])->toBe($this->selectedBrand);
    expect($lock['front_theme'])->toBe($this->selectedTheme);
});

test('installer rejects an unknown brand', function (): void {
    $response = $this->post(route('install.store-admin'), [
        'name' => 'Bad Brand',
        'email' => 'bad-brand@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'Bad Brand',
        'admin_path' => 'panel',
        'brand' => 'not-a-real-brand',
    ]);

    $response->assertSessionHasErrors('brand');
});

test('installer completion requires the installation lock even with a completion session', function (bool $justInstalled): void {
    $this->withSession(['install.just_installed' => $justInstalled])
        ->get(route('install.step5'))
        ->assertRedirect(route('install.step1'));
})->with([false, true]);
