<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\PageTranslation;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DemoComposition;
use Tests\TestCase;

class DemoSeederOwnershipTest extends TestCase
{
    use DemoComposition, RefreshDatabase;

    public function test_foreign_demo_slug_is_rejected_before_lifecycle_or_content_writes(): void
    {
        $page = Page::create(['template' => 'default', 'layout' => 'app', 'is_published' => false]);
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'en', 'slug' => 'how-it-works', 'title' => 'My page']);
        $modules = config('cms.modules');
        try {
            $this->seed(PlatformShowcaseSeeder::class);
            $this->fail('A foreign page was adopted by the demo.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Demo identity collision', $exception->getMessage());
        }
        $this->assertSame($modules, config('cms.modules'));
        $this->assertFalse(Menu::where('slug', 'header')->exists());
        $this->assertNull(Setting::getValue(PlatformShowcaseSeeder::INVENTORY_KEY));
        $this->assertFalse($page->fresh()->is_published);
        $this->assertSame('My page', $page->translations()->firstOrFail()->title);
    }

    public function test_demo_reuses_an_installer_role_without_claiming_it_or_removing_its_grants(): void
    {
        $role = Role::create(['slug' => 'admin', 'name' => 'Installation administrator', 'description' => 'Managed by installation']);
        $permission = Permission::create(['slug' => 'installation:custom', 'name' => 'Installation permission']);
        $role->permissions()->attach($permission);

        $this->seed(PlatformShowcaseSeeder::class);
        $this->seed(PlatformShowcaseSeeder::class);

        $this->assertSame('Installation administrator', $role->fresh()->name);
        $this->assertTrue($role->permissions()->whereKey($permission->id)->exists());
        $this->assertArrayNotHasKey('role:admin', Setting::getValue(PlatformShowcaseSeeder::INVENTORY_KEY));
        $this->assertTrue($role->permissions()->where('slug', 'manage:pages')->exists());
    }
}
