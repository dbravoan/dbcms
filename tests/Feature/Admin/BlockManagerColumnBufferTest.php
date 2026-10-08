<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\SectionTranslation;
use Tests\TestCase;

class BlockManagerColumnBufferTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformShowcaseSeeder::class);

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
                    'layout' => '2-cols',
                    'columns' => [
                        [
                            'id' => 'col-1',
                            'widgets' => [
                                ['type' => 'text', 'content' => ['body' => 'Columna 1']],
                            ],
                        ],
                        [
                            'id' => 'col-2',
                            'widgets' => [
                                ['type' => 'text', 'content' => ['body' => 'Columna 2']],
                            ],
                        ],
                    ],
                    '_col_buffer' => [
                        [
                            'id' => 'col-3-stashed',
                            'widgets' => [
                                ['type' => 'text', 'content' => ['body' => 'Columna 3 stashed']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Perform page update via standard Admin PutController
        $response = $this->actingAs($this->adminUser, 'web')
            ->put(route('admin.pages.update', $page->id), [
                'title' => 'Página Actualizada',
                'is_active' => true,
                'sections_json' => json_encode($payloadWithBuffer),
                'markets' => [1],
            ]);

        $response->assertRedirect();

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
    }
}
