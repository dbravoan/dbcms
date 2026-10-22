<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\SectionTranslation;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;
use Tests\Support\VisualContentFixtures;
use Tests\TestCase;

class BlockManagerColumnBufferTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        CmsFixtures::localization();
        CmsFixtures::identities();
        VisualContentFixtures::home();

        /** @var User $admin */
        $admin = User::where('email', 'admin@db-cms.io')->first();
        $this->adminUser = $admin;
    }

    /**
     * Verify that sections saved via the backoffice entity update never persist _col_buffer in the database.
     */
    public function test_col_buffer_is_never_persisted_in_database_sections(): void
    {
        /** @var Page $page */
        $page = Page::firstOrFail();

        // Simulate incoming payload from client that had buffer data or clean serialized sections
        $payloadWithBuffer = [
            'es' => [
                [
                    'id' => 'section-1',
                    'title' => 'Sección de Prueba',
                    'type' => 'features',
                    'columns_layout' => '2',
                    'columns' => [
                        [
                            'id' => 'col-1',
                            'blocks' => [
                                ['type' => 'text', 'content' => 'Columna 1'],
                            ],
                        ],
                        [
                            'id' => 'col-2',
                            'blocks' => [
                                ['type' => 'text', 'content' => 'Columna 2'],
                            ],
                        ],
                    ],
                    '_col_buffer' => [
                        [
                            'id' => 'col-3-stashed',
                            'blocks' => [
                                ['type' => 'text', 'content' => 'Columna 3 stashed'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Perform page update via standard Admin PutController
        $response = $this->actingAs($this->adminUser, 'web')
            ->put(route('admin.pages.update', $page->id), [
                'translations' => ['es' => ['title' => 'Página Actualizada', 'slug' => 'home']],
                'is_published' => true,
                'sections_json' => json_encode($payloadWithBuffer),
                'market_ids' => Market::pluck('id')->all(),
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        // Reload page and check stored sections
        $page->refresh();

        // Ensure database section translations never contain '_col_buffer'
        $allTranslationsJson = json_encode(
            SectionTranslation::whereIn(
                'page_section_id',
                $page->sections()->pluck('id')
            )->pluck('content_json')->all()
        );
        $this->assertStringNotContainsString('_col_buffer', (string) $allTranslationsJson);
        $this->assertStringContainsString('Columna 1', (string) $allTranslationsJson);
    }
}
