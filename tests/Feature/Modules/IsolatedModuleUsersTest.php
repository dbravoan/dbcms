<?php

declare(strict_types=1);

use App\Core\Modules\BaseModuleServiceProvider;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);

    // Create temporary isolated module authenticatable class for testing
    if (! class_exists('TestCustomerUser')) {
        eval('
            class TestCustomerUser extends \Illuminate\Foundation\Auth\User {
                use \Laravel\Passport\HasApiTokens;
                protected $table = "test_module_customers";
                protected $fillable = ["name", "email", "password"];
            }
        ');
    }

    Schema::create('test_module_customers', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('test_module_customers');

    $testModulePath = base_path('modules/TestScaffoldModule');
    if (File::exists($testModulePath)) {
        File::deleteDirectory($testModulePath);
    }
});

test('module service provider can register isolated auth provider and guards dynamically', function (): void {
    $provider = new class(app()) extends BaseModuleServiceProvider
    {
        protected string $identifier = 'test_shop';

        protected string $name = 'TestShop';

        public function register(): void
        {
            $this->registerModuleAuth(
                'test_shop_customers',
                'TestCustomerUser',
                'test_customer',
                'test_customer_api'
            );
        }
    };

    $provider->register();

    // Verify config dynamic injection
    expect(Config::get('auth.providers.test_shop_customers.model'))->toBe('TestCustomerUser');
    expect(Config::get('auth.guards.test_customer.provider'))->toBe('test_shop_customers');
    expect(Config::get('auth.guards.test_customer_api.driver'))->toBe('passport');
    expect(Config::get('auth.guards.test_customer_api.provider'))->toBe('test_shop_customers');
});

test('isolated module users authenticate independently from core CMS users', function (): void {
    // 1. Setup isolated provider and guards
    Config::set('auth.providers.test_shop_customers', [
        'driver' => 'eloquent',
        'model' => 'TestCustomerUser',
    ]);
    Config::set('auth.guards.test_customer', [
        'driver' => 'session',
        'provider' => 'test_shop_customers',
    ]);

    // 2. Create customer and core user
    /** @var TestCustomerUser $customer */
    $customer = TestCustomerUser::create([
        'name' => 'Cliente Aislado',
        'email' => 'cliente@tienda.io',
        'password' => bcrypt('password123'),
    ]);

    $coreUser = User::first();

    // 3. Authenticate customer on module guard
    Auth::guard('test_customer')->login($customer);

    expect(Auth::guard('test_customer')->check())->toBeTrue();
    expect(Auth::guard('test_customer')->user()->id)->toBe($customer->id);

    // 4. Verify core web guard remains completely unaffected
    expect(Auth::guard('web')->check())->toBeFalse();
    expect(Auth::guard('web')->user())->toBeNull();
});

test('make:cms-module artisan command scaffolds isolated users and dedicated portal', function (): void {
    $exitCode = Artisan::call('make:cms-module', [
        'name' => 'TestScaffoldModule',
        '--with-auth' => true,
        '--dedicated-panel' => true,
    ]);

    expect($exitCode)->toBe(0);

    $basePath = base_path('modules/TestScaffoldModule');
    expect(File::exists($basePath))->toBeTrue();

    // Verify Authenticatable model
    expect(File::exists($basePath.'/Models/TestScaffoldModuleUser.php'))->toBeTrue();
    $modelContent = (string) File::get($basePath.'/Models/TestScaffoldModuleUser.php');
    expect($modelContent)->toContain('use HasApiTokens');
    expect($modelContent)->toContain('class TestScaffoldModuleUser extends Authenticatable');

    // Verify Portal Controller
    expect(File::exists($basePath.'/Http/Controllers/Portal/TestScaffoldModulePortalController.php'))->toBeTrue();

    // Verify Portal Views
    expect(File::exists($basePath.'/Resources/views/portal/layout.blade.php'))->toBeTrue();
    expect(File::exists($basePath.'/Resources/views/portal/dashboard.blade.php'))->toBeTrue();
    expect(File::exists($basePath.'/Resources/views/portal/login.blade.php'))->toBeTrue();

    // Verify Module Service Provider registers isolated auth and dedicated panel
    $spContent = (string) File::get($basePath.'/TestScaffoldModuleModuleServiceProvider.php');
    expect($spContent)->toContain('registerModuleAuth');
    expect($spContent)->toContain('hasDedicatedPanel');
    expect($spContent)->toContain('getDedicatedPanelUrl');
});
