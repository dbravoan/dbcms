<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Content\Pages\Models\Page;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Search\Contracts\SearchSourceInterface;
use App\Core\Search\Contracts\SearchSourceRegistryInterface;
use App\Core\Search\SearchEngine;
use App\Core\Search\SearchIndexer;
use App\Models\SearchDocumentIndex;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\Support\CmsFixtures;
use Tests\TestCase;

final class ModuleBlockAvailabilityIndexTest extends TestCase
{
    use DatabaseMigrations;

    public const TEST_MODULES = ['forms', 'search'];

    public function test_module_toggle_reindexes_authored_block_text_in_core_pages(): void
    {
        CmsFixtures::localization(['es']);
        $page = Page::withoutEvents(function (): Page {
            $page = CmsFixtures::home();
            $page->updateQuietly(['published_at' => now()->subMinute()]);
            $section = $page->sections()->create(['type' => 'columns', 'status' => 'published', 'is_active' => true, 'order' => 1, 'styling_json' => ['layout' => '1']]);
            $section->translations()->create(['locale' => 'es', 'content_json' => ['columns' => [['blocks' => [['type' => 'widget_form', 'title' => 'Moduleonlyneedle']]]]]]);

            return $page;
        });
        app(SearchIndexer::class)->indexAll();
        $search = fn (): int => app(SearchEngine::class)->search('Moduleonlyneedle', 'es', market: 'es')->total;
        self::assertSame(1, $search());

        app(ModuleManager::class)->deactivate('forms');
        self::assertSame(0, $search());
        self::assertSame(1, $page->sections()->count());
        app(ModuleManager::class)->activate('forms');
        self::assertSame(1, $search());
        app(ModuleManager::class)->uninstall('forms');
        self::assertSame(0, $search());
    }

    public function test_failed_reindex_rolls_back_module_availability_and_index_together(): void
    {
        CmsFixtures::localization(['es']);
        CmsFixtures::home();
        app(SearchIndexer::class)->indexAll();
        $before = SearchDocumentIndex::get()->toArray();
        $source = \Mockery::mock(SearchSourceInterface::class);
        $source->shouldReceive('type')->andReturn('failing-projection');
        $source->shouldReceive('documents')->andThrow(new \RuntimeException('Projection failed'));
        app(SearchSourceRegistryInterface::class)->register($source);

        try {
            app(ModuleManager::class)->deactivate('forms');
            self::fail('A failed projection must abort the availability change.');
        } catch (\RuntimeException $error) {
            self::assertSame('Projection failed', $error->getMessage());
        }
        self::assertTrue(app(ModuleManager::class)->isModuleEnabled('forms'));
        self::assertSame($before, SearchDocumentIndex::get()->toArray());
    }

    public function test_availability_setting_reindexes_blocks_and_restarts_workers_after_commit(): void
    {
        $this->withAvailabilitySetting(function (): void {
            CmsFixtures::localization(['es']);
            $page = CmsFixtures::home();
            $section = $page->sections()->create(['type' => 'columns', 'status' => 'published', 'is_active' => true, 'order' => 0]);
            $section->translations()->create(['locale' => 'es', 'content_json' => ['columns' => [['blocks' => [['type' => 'widget_form', 'title' => 'Settingsneedle']]]]]]);
            app(SearchIndexer::class)->indexAll();
            $body = fn (): string => (string) SearchDocumentIndex::where('type', 'page')->where('entity_id', $page->id)->value('body');
            self::assertStringContainsString('Settingsneedle', $body());
            Cache::forget('illuminate:queue:restart');

            app(ModuleSettingsServiceInterface::class)->set('forms', 'is_enabled', false);
            self::assertStringNotContainsString('Settingsneedle', $body());
            self::assertFalse(config('modules.forms.is_enabled'));
            self::assertIsInt(Cache::get('illuminate:queue:restart'));
            app(ModuleSettingsServiceInterface::class)->set('forms', 'is_enabled', true);
            self::assertStringContainsString('Settingsneedle', $body());
        });
    }

    public function test_failed_settings_projection_preserves_database_config_and_resolved_settings(): void
    {
        $this->withAvailabilitySetting(function (): void {
            $settings = app(ModuleSettingsServiceInterface::class);
            $settings->set('forms', 'is_enabled', true);
            $source = \Mockery::mock(SearchSourceInterface::class);
            $source->shouldReceive('type')->andReturn('failing-settings-projection');
            $source->shouldReceive('documents')->andReturnUsing(static function () use ($settings): never {
                self::assertFalse($settings->get('forms', 'is_enabled'));
                throw new \RuntimeException('Projection failed');
            });
            app(SearchSourceRegistryInterface::class)->register($source);
            Cache::forget('illuminate:queue:restart');
            try {
                $settings->set('forms', 'is_enabled', false);
                self::fail('A failed projection must abort the settings change.');
            } catch (\RuntimeException $error) {
                self::assertSame('Projection failed', $error->getMessage());
            }
            self::assertTrue(app(ModuleManager::class)->isModuleEnabled('forms'));
            self::assertTrue($settings->get('forms', 'is_enabled'));
            self::assertTrue(config('modules.forms.is_enabled'));
            self::assertNull(Cache::get('illuminate:queue:restart'));
        });
    }

    private function withAvailabilitySetting(callable $test): void
    {
        $root = storage_path('framework/testing-availability-'.bin2hex(random_bytes(6)));
        foreach (['Forms', 'Search'] as $name) {
            File::ensureDirectoryExists($root.'/'.$name);
            $manifest = json_decode(File::get(base_path('modules/'.$name.'/module.json')), true, flags: JSON_THROW_ON_ERROR);
            if ($name === 'Forms') {
                $manifest['availability_setting'] = 'is_enabled';
                $manifest['settings']['is_enabled'] = ['type' => 'boolean', 'default' => true];
            }
            File::put($root.'/'.$name.'/module.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        }
        $previous = config('cms.module_packages.root');
        config(['cms.module_packages.root' => $root]);
        try {
            $test();
        } finally {
            config(['cms.module_packages.root' => $previous]);
            File::deleteDirectory($root);
        }
    }
}
