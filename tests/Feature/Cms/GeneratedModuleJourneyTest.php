<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Models\CmsModule;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Modules\JourneyEvent\JourneyEventModuleServiceProvider;
use Modules\JourneyEvent\Models\JourneyEvent;
use Modules\JourneyPortal\Models\JourneyPortalUser;
use Symfony\Component\Process\Process;
use Tests\Support\CmsFixtures;
use Tests\TestCase;

/** Executes the fresh scaffold, without tutorial patches, only in isolated CI. */
final class GeneratedModuleJourneyTest extends TestCase
{
    use RefreshDatabase;

    public const TEST_MODULES = ['journey-event', 'journey-portal'];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (getenv('DBCMS_TEST_ISOLATED') !== '1') {
            self::markTestSkipped('Generated filesystem/database journeys require the disposable CI runtime.');
        }
        foreach (['JourneyEvent' => ['--pageable', '--crud'], 'JourneyPortal' => ['--dedicated-panel']] as $name => $options) {
            $directory = dirname(__DIR__, 3).'/modules/'.$name;
            self::assertDirectoryDoesNotExist($directory, 'Never overwrite an existing module during scaffold verification.');
            $process = new Process([PHP_BINARY, 'artisan', 'make:cms-module', $name, ...$options], dirname(__DIR__, 3), ['CMS_MODULES' => '']);
            $process->mustRun();
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (['JourneyEvent', 'JourneyPortal'] as $name) {
            File::deleteDirectory(dirname(__DIR__, 3).'/modules/'.$name);
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CmsFixtures::localization(['es', 'en'], ['es', 'uk']);
        CmsFixtures::identities();
    }

    public function test_generated_crud_opens_both_forms_and_persists_the_public_record(): void
    {
        $this->actingAs(User::where('email', 'admin@db-cms.io')->sole());
        $this->get('/admin/journey-event')->assertOk();
        foreach (['dbcms', 'termocms'] as $brand) {
            app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
            $this->get('/admin/journey-event/create')->assertOk()->assertSee('name="is_active" value="0"', false);
        }
        $market = Market::where('code', 'es')->sole();
        $data = ['translations' => ['es' => ['title' => 'Evento real', 'slug' => 'evento-real', 'content' => 'Contenido público']], 'market_ids' => [$market->id], 'is_active' => true];
        $this->post('/admin/journey-event', $data)->assertRedirect('/admin/journey-event')->assertSessionHasNoErrors();
        $item = JourneyEvent::sole();
        $this->get('/admin/journey-event/'.$item->id.'/edit')->assertOk()->assertSee('Evento real');
        $this->get('/es/es/journey-event')->assertOk()->assertSee('Evento real')->assertSee('Contenido público');
        $this->get('/es/en/journey-event')->assertOk()->assertDontSee('Evento real');
        $data['is_active'] = false;
        $this->put('/admin/journey-event/'.$item->id, $data)->assertRedirect('/admin/journey-event');
        $this->assertFalse($item->fresh()->is_active);
        $this->get('/es/es/journey-event')->assertOk()->assertDontSee('Evento real');
        $this->delete('/admin/journey-event/'.$item->id)->assertRedirect('/admin/journey-event');
        $this->assertDatabaseMissing('journey_events', ['id' => $item->id]);
    }

    public function test_generated_public_reader_isolates_market_language_and_active_state(): void
    {
        $es = Market::where('code', 'es')->sole();
        $uk = Market::where('code', 'uk')->sole();
        foreach ([['INACTIVE', false, 'es', $es], ['OTHER MARKET', true, 'es', $uk], ['OTHER LANGUAGE', true, 'en', $es], ['VISIBLE', true, 'es', $es]] as [$title, $active, $locale, $market]) {
            $item = JourneyEvent::create(['is_active' => $active]);
            $item->markets()->sync([$market->id]);
            $item->translations()->create(['locale' => $locale, 'title' => $title, 'slug' => strtolower(str_replace(' ', '-', $title))]);
        }
        $this->get('/es/es/journey-event')->assertOk()->assertSee('VISIBLE')->assertDontSee('INACTIVE')->assertDontSee('OTHER MARKET')->assertDontSee('OTHER LANGUAGE');
        CmsModule::where('identifier', 'journey-event')->update(['is_active' => false]);
        $this->get('/es/es/journey-event')->assertNotFound();
    }

    public function test_generated_routes_never_consume_the_configured_admin_prefix_as_locale(): void
    {
        $originalRoutes = Route::getRoutes();
        Route::setRoutes(new RouteCollection);
        try {
            config(['cms.admin_path' => 'ops']);
            (new JourneyEventModuleServiceProvider(app()))->boot();
            $route = Route::getRoutes()->match(Request::create('/ops/journey-event'));
            self::assertStringContainsString('Admin\\JourneyEventGetController', $route->getActionName());
        } finally {
            Route::setRoutes($originalRoutes);
        }
    }

    public function test_generated_portal_rejects_inactive_login_and_revokes_existing_sessions(): void
    {
        $user = JourneyPortalUser::create(['name' => 'Portal account', 'email' => 'portal@example.test', 'password' => 'password-test', 'is_active' => false]);
        $this->post('/journey-portal/login', ['email' => $user->email, 'password' => 'password-test'])->assertSessionHasErrors('email');
        $this->assertGuest('journey-portal');
        $user->update(['is_active' => true]);
        $this->post('/journey-portal/login', ['email' => $user->email, 'password' => 'password-test'])->assertRedirect(route('journey-portal.portal'));
        $this->get('/journey-portal/portal')->assertOk()->assertSee('Portal account');
        $user->update(['is_active' => false]);
        $this->get('/journey-portal/portal')->assertRedirect(route('journey-portal.login'));
        $this->assertGuest('journey-portal');
    }

    public function test_unmodified_generated_sources_pass_the_required_static_analysis(): void
    {
        $process = new Process([PHP_BINARY, 'vendor/bin/phpstan', 'analyse', 'modules/JourneyEvent', 'modules/JourneyPortal', '--memory-limit=2G', '--no-progress'], base_path());
        $process->setTimeout(120);
        $process->run();
        self::assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
    }
}
