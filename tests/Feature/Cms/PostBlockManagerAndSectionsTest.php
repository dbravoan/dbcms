<?php

declare(strict_types=1);

use App\Core\Sections\SectionEditorService;
use App\Models\Market;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostSection;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('admin can create a blog post with modular sections and block manager', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->first();
    $market = Market::where('code', 'es')->first();
    $category = Category::first();

    $sections = [
        [
            'type' => 'hero',
            'title' => 'Introducción Revolucionaria a dbCMS',
            'content' => 'Descubre la arquitectura modular para CMS modernos.',
            'cta_text' => 'Comenzar Tour',
            'cta_url' => '#tour',
            'animation' => 'fadeInDown',
            'animation_delay' => '0.2s',
            'container_style' => 'neon-glow',
        ],
        [
            'type' => 'text',
            'title' => 'Análisis Profundo de Rendimiento',
            'content' => "## Rendimiento en PHP 8.3\n\nEl sistema soporta **millones de peticiones** con latencia sub-50ms.",
            'animation' => 'fadeInUp',
            'animation_delay' => '0.4s',
            'container_style' => 'cyber-card',
        ],
        [
            'type' => 'faq',
            'title' => 'Preguntas Técnicas',
            'items' => [
                ['question' => '¿Qué es GEO?', 'answer' => 'Generative Engine Optimization para Perplexity y ChatGPT.'],
            ],
            'animation' => 'zoomIn',
            'animation_delay' => '0.6s',
            'container_style' => 'cyber-card',
        ],
        [
            'type' => 'cta',
            'title' => '¿Listo para construir?',
            'content' => 'Prueba dbCMS hoy mismo.',
            'cta_text' => 'Contactar Soporte',
            'cta_url' => '/es/es/contacto',
            'animation' => 'bounceIn',
            'animation_delay' => '0.8s',
            'container_style' => 'full-width-dark',
        ],
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.blog.posts.store'), [
            'translations' => ['es' => [
                'title' => 'Guía Completa de Bloques Modulares en Blog',
                'slug' => 'guia-completa-bloques-modulares-blog',
                'summary' => 'Artículo técnico con bloques dinámicos y ScrollMagic.',
            ]],
            'category_id' => $category->id,
            'market_ids' => [$market->id],
            'is_published' => true,
            'sections_json' => json_encode($sections),
        ]);

    $response->assertRedirect(route('admin.blog.posts.index'));
    $response->assertSessionHas('success');

    $post = Post::whereHas('translations', fn ($q) => $q->where('slug', 'guia-completa-bloques-modulares-blog'))->first();
    expect($post)->not->toBeNull();
    expect($post->sections()->count())->toBe(4);

    $heroSec = $post->sections()->where('type', 'hero')->first();
    expect($heroSec)->not->toBeNull();
    expect($heroSec->styling_json['animation'])->toBe('fadeInDown');
    expect($heroSec->translations()->where('locale', 'es')->first()->content_json['title'])->toBe('Introducción Revolucionaria a dbCMS');
});

test('admin can update an existing blog post and re-sync its sections', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->first();
    $post = Post::firstOrFail();
    expect($post->is_published)->toBeTrue();
    $liveSections = app(SectionEditorService::class)->payload($post);
    $liveTranslation = $post->translations()->where('locale', 'es')->firstOrFail()->only(['title', 'slug', 'summary', 'content']);

    $updatedSections = [
        [
            'type' => 'text',
            'title' => 'Sección Actualizada',
            'content' => 'Contenido renovado con nuevo texto técnico.',
            'animation' => 'fadeInLeft',
            'animation_delay' => '0.2s',
            'container_style' => 'cyber-glass',
        ],
        [
            'type' => 'widget_stats',
            'title' => 'Estadísticas en Vivo',
            'items' => [
                ['value' => '99.99%', 'label' => 'Uptime'],
                ['value' => '10M+', 'label' => 'Lecturas'],
            ],
            'animation' => 'flipInX',
            'animation_delay' => '0.4s',
            'container_style' => 'neon-glow',
        ],
    ];

    $response = $this->actingAs($admin)
        ->put(route('admin.blog.posts.update', $post), [
            'translations' => ['es' => [
                'title' => 'Artículo con Secciones Actualizadas',
                'slug' => 'articulo-secciones-actualizadas',
                'summary' => 'Resumen actualizado.',
            ]],
            'is_published' => true,
            'sections_json' => json_encode($updatedSections),
        ]);

    $response->assertRedirect(route('admin.blog.posts.index'));
    $response->assertSessionHas('success');

    $response->assertSessionHasNoErrors();
    $post->refresh();
    expect($post->is_published)->toBeTrue();
    expect(app(SectionEditorService::class)->payload($post))->toBe($liveSections);
    expect($post->translations()->where('locale', 'es')->firstOrFail()->only(['title', 'slug', 'summary', 'content']))->toBe($liveTranslation);
    $draft = app(PostDomainServiceInterface::class)->editData($post)['post'];
    expect($draft->sections)->toHaveCount(2);
    expect($draft->sections->firstWhere('type', 'widget_stats'))->not->toBeNull();
    expect($draft->translations->firstWhere('locale', 'es')->title)->toBe('Artículo con Secciones Actualizadas');
    expect($draft->translations->firstWhere('locale', 'es')->summary)->toBe('Resumen actualizado.');
    $pendingSections = app(SectionEditorService::class)->payload($draft);
    expect(json_encode($pendingSections, JSON_UNESCAPED_UNICODE))->toContain('Contenido renovado con nuevo texto técnico.')->toContain('flipInX');
    $this->get(route('admin.blog.posts.edit', $post))->assertOk()->assertViewHas('post', fn (Post $editor): bool => app(SectionEditorService::class)->payload($editor) === $pendingSections);
    app(ContentAuditServiceInterface::class)->approve($post, $admin);
    $post->refresh();
    expect($post->sections()->count())->toBe(2);
    expect($post->sections()->where('type', 'widget_stats')->exists())->toBeTrue();
    expect(app(SectionEditorService::class)->payload($post))->toBe($pendingSections);
    expect($post->translations()->where('locale', 'es')->firstOrFail()->title)->toBe('Artículo con Secciones Actualizadas');
    expect($post->is_published)->toBeTrue();
});

test('frontoffice renders a modular blog post with sections and ScrollMagic effects', function (): void {
    $market = Market::where('code', 'es')->first();
    $admin = User::where('email', 'admin@db-cms.io')->first();

    $sections = [
        [
            'type' => 'hero',
            'title' => 'Hero Modular del Post',
            'content' => 'Bajada del hero en el artículo.',
            'animation' => 'fadeInUp',
            'container_style' => 'neon-glow',
        ],
        [
            'type' => 'faq',
            'title' => 'Preguntas del Post',
            'items' => [
                ['question' => '¿Cómo se optimiza para IA?', 'answer' => 'Generando marcado semántico estructurado.'],
            ],
            'animation' => 'fadeInUp',
            'container_style' => 'cyber-card',
        ],
    ];

    $this->actingAs($admin)->post(route('admin.blog.posts.store'), [
        'translations' => ['es' => [
            'title' => 'Artículo Modular para Frontoffice',
            'slug' => 'articulo-modular-frontoffice',
            'summary' => 'Resumen para frontoffice.',
        ]],
        'market_ids' => [$market->id],
        'is_published' => true,
        'sections_json' => json_encode($sections),
    ]);

    $response = $this->get('/es/es/blog/articulo-modular-frontoffice');
    $response->assertOk();
    $response->assertSee('Hero Modular del Post');
    $response->assertSee('Preguntas del Post');
    $response->assertSee('¿Cómo se optimiza para IA?');
    $response->assertSee('data-scroll-magic', false);
});

test('frontoffice gracefully renders blog post without sections via legacy body fallback', function (): void {
    $post = Post::published()->first();
    // Ensure this post has no modular sections
    $post->sections()->delete();

    $trans = $post->translations()->where('locale', 'es')->first();

    $response = $this->get("/es/es/blog/{$trans->slug}");
    $response->assertOk();
    $response->assertSee($trans->title);
    expect($response->getContent())->not->toBeEmpty();
});

test('deleting a blog post cascades and removes all its post_sections', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->first();
    $post = Post::first();

    PostSection::create([
        'post_id' => $post->id,
        'type' => 'text',
        'order' => 1,
        'is_active' => true,
        'styling_json' => ['title' => 'Temporal'],
    ]);

    expect(PostSection::where('post_id', $post->id)->count())->toBeGreaterThan(0);

    $response = $this->actingAs($admin)->delete(route('admin.blog.posts.destroy', $post));
    $response->assertRedirect(route('admin.blog.posts.index'));

    expect(PostSection::where('post_id', $post->id)->count())->toBe(0);
});
