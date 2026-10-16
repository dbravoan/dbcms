<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\PageSection;
use App\Content\Pages\Models\PageTranslation;
use App\Content\Pages\Models\SectionTranslation;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Search\SearchIndexer;
use App\Models\Market;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\CategoryTranslation;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Models\CustomFormTranslation;
use Modules\Forms\Models\FormField;
use Modules\Forms\Models\FormFieldTranslation;
use Modules\Forms\Models\FormSubmission;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Models\GalleryItem;
use Modules\Gallery\Models\GalleryTranslation;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Models\ProductCategoryTranslation;
use Modules\Shop\Models\ProductTranslation;

/**
 * Narrative demo: the platform explaining itself.
 *
 * Instead of marketing filler, the seeded content is a tour of the product:
 * what the CMS is, how the multi-market matrix works, and one section + one
 * article per module (pages, blog, shop, gallery, forms, search, MCP, AI).
 *
 * Runs on top of {@see CmsFoundationSeeder} (languages and markets),
 * is idempotent, and every visible string follows the active product brand.
 *
 * Content matrix:
 *   - Full narrative in `es` and `en` (the multi-market story is told here).
 *   - `fr`, `it`, `pt`, `de` get the home, the modules page and one flagship post.
 *   - `zh-CN` and `ar` get the home and the modules page (Arabic exercises RTL).
 */
class PlatformShowcaseSeeder extends Seeder
{
    /** Explicit composition of the demonstration site, not the core default. */
    public const MODULES = ['blog', 'forms', 'gallery', 'shop', 'search', 'mcp', 'ai_assistant', 'content_audit', 'marketing', 'analytics', 'orchestration'];

    public function run(): void
    {
        app(SearchIndexer::class)->batch(fn () => $this->seedShowcase());
    }

    protected function seedShowcase(): void
    {
        $this->call(CmsFoundationSeeder::class);

        $brand = (string) config('cms.name', 'CMS');
        $defaultName = $brand;

        $marketEu = Market::where('code', 'eu')->firstOrFail();
        $marketUk = Market::where('code', 'uk')->firstOrFail();
        $marketUs = Market::where('code', 'us')->firstOrFail();
        $marketMx = Market::where('code', 'mx')->firstOrFail();
        $marketCo = Market::where('code', 'co')->firstOrFail();
        $marketCn = Market::where('code', 'cn')->firstOrFail();
        $marketGcc = Market::where('code', 'gcc')->firstOrFail();

        // Every active market, including the legacy `es` entry: content is
        // global unless a piece explicitly narrows its markets.
        /** @var list<int> $regions */
        $regions = array_values(
            Market::query()->where('is_active', true)->orderBy('id')->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all()
        );

        $this->registerModules();
        $this->seedRolesAndUsers();
        $this->seedMenus($regions);
        $this->seedContactForm($regions);
        $this->seedBlog($regions);
        $this->seedGallery($regions);
        $this->seedShop($regions);
        $this->seedPages($regions, $brand);

        $this->command?->info("{$brand}: platform showcase seeded across ".count($regions).' markets.');
    }

    /**
     * Every module the demo exercises, with its localized, useful description.
     */
    protected function registerModules(): void
    {
        config(['cms.modules' => self::MODULES]);
        app(ModuleManager::class)->installConfiguredModules();
    }

    protected function seedRolesAndUsers(): void
    {
        // Permission catalogue enforced by the routes/middleware.
        $permissions = [
            'access:admin' => 'Acceder al panel de administración',
            'manage:users' => 'Gestionar usuarios y roles',
            'manage:modules' => 'Gestionar módulos',
            'manage:blocks' => 'Gestionar paquetes de bloques',
            'manage:themes' => 'Gestionar plantillas y temas',
            'manage:pages' => 'Gestionar páginas',
            'manage:posts' => 'Gestionar artículos y categorías',
            'manage:content' => 'Gestionar contenido',
            'manage:media' => 'Gestionar archivos multimedia',
            'manage:galleries' => 'Gestionar galerías',
            'manage:menus' => 'Gestionar menús y zonas',
            'manage:forms' => 'Gestionar formularios y contactos',
            'manage:shop' => 'Gestionar tienda y catálogo',
            'manage:seo' => 'Gestionar SEO y filtros',
            'manage:storage' => 'Gestionar almacenamiento externo',
            'use:ai' => 'Utilizar las capacidades IA autorizadas',
            'manage:ai' => 'Administrar proveedores y configuración IA',
        ];

        $permissionModels = [];
        foreach ($permissions as $slug => $name) {
            $permissionModels[$slug] = Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        // Role => permissions it grants.
        $roles = [
            'admin' => ['Administrador', 'Acceso total sin restricciones', array_keys($permissions)],
            'editor' => ['Editor de contenido', 'Gestión de artículos, páginas y menús', [
                'access:admin', 'manage:pages', 'manage:posts', 'manage:content', 'manage:media', 'manage:galleries', 'manage:menus', 'manage:seo', 'use:ai',
            ]],
            'author' => ['Autor o redactor', 'Creación de artículos para el blog', [
                'access:admin', 'manage:posts', 'manage:media', 'manage:galleries', 'use:ai',
            ]],
            'marketing' => ['Marketing y captación', 'Formularios, contactos y auditoría SEO', [
                'access:admin', 'manage:forms', 'manage:seo', 'manage:media', 'manage:galleries', 'use:ai',
            ]],
            'viewer' => ['Lector (auditor)', 'Acceso de solo lectura para evaluación', [
                'access:admin',
            ]],
        ];

        foreach ($roles as $slug => [$name, $description, $grants]) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description]
            );

            $ids = [];
            foreach ($grants as $permissionSlug) {
                if (isset($permissionModels[$permissionSlug])) {
                    $ids[] = $permissionModels[$permissionSlug]->id;
                }
            }

            $role->permissions()->sync($ids);
        }

        $users = [
            ['name' => 'Admin', 'email' => 'admin@db-cms.io', 'role' => 'admin'],
            ['name' => 'Editor', 'email' => 'editor@db-cms.io', 'role' => 'editor'],
            ['name' => 'Author', 'email' => 'author@db-cms.io', 'role' => 'author'],
            ['name' => 'Marketing', 'email' => 'marketing@db-cms.io', 'role' => 'marketing'],
            ['name' => 'Viewer', 'email' => 'viewer@db-cms.io', 'role' => 'viewer'],
        ];

        foreach ($users as $definition) {
            $user = User::firstOrCreate(
                ['email' => $definition['email']],
                ['name' => $definition['name'], 'password' => Hash::make('secret123')]
            );
            $role = Role::where('slug', $definition['role'])->first();
            if ($role !== null) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }

    /**
     * Menus: primary navigation that literally walks the visitor through the
     * modules, plus the footer link groups.
     *
     * @param  list<int>  $regions
     */
    protected function seedMenus(array $regions): void
    {
        $header = Menu::firstOrCreate(['slug' => 'header'], ['name' => 'Menú principal (cabecera)', 'location' => 'header', 'is_active' => true]);
        $header->markets()->sync($regions);
        $this->seedMenuItems($header, [
            $this->menuItem(['es' => 'Inicio', 'en' => 'Home', 'fr' => 'Accueil', 'it' => 'Inizio', 'pt' => 'Início', 'de' => 'Startseite', 'zh-CN' => '首页', 'ar' => 'الرئيسية'], '/', 'custom_url'),
            $this->menuItem(['es' => 'Cómo funciona', 'en' => 'How it works', 'fr' => 'Comment ça marche', 'it' => 'Come funziona', 'pt' => 'Como funciona', 'de' => 'So funktioniert es', 'zh-CN' => '工作原理', 'ar' => 'كيف تعمل المنصة'], 'como-funciona', 'page'),
            $this->menuItem(['es' => 'Módulos', 'en' => 'Modules', 'fr' => 'Modules', 'it' => 'Moduli', 'pt' => 'Módulos', 'de' => 'Module', 'zh-CN' => '模块', 'ar' => 'الوحدات'], 'modulos', 'page'),
            $this->menuItem(['es' => 'Bloques', 'en' => 'Blocks', 'fr' => 'Blocs', 'it' => 'Blocchi', 'pt' => 'Blocos', 'de' => 'Blöcke', 'zh-CN' => '区块', 'ar' => 'الوحدات والمكونات'], 'bloques', 'page'),
            $this->menuItem(['es' => 'Blog', 'en' => 'Blog', 'fr' => 'Blog', 'it' => 'Blog', 'pt' => 'Blogue', 'de' => 'Blog', 'zh-CN' => '博客', 'ar' => 'المدونة'], '/blog', 'blog'),
            $this->menuItem(['es' => 'Tienda', 'en' => 'Shop', 'fr' => 'Boutique', 'it' => 'Negozio', 'pt' => 'Loja', 'de' => 'Shop', 'zh-CN' => '商店', 'ar' => 'المتجر'], '/shop', 'shop'),
            $this->menuItem(['es' => 'Galería', 'en' => 'Gallery', 'fr' => 'Galerie', 'it' => 'Galleria', 'pt' => 'Galeria', 'de' => 'Galerie', 'zh-CN' => '图库', 'ar' => 'المعرض'], '/galeria', 'gallery'),
            $this->menuItem(['es' => 'Contacto', 'en' => 'Contact', 'fr' => 'Contact', 'it' => 'Contatto', 'pt' => 'Contacto', 'de' => 'Kontakt', 'zh-CN' => '联系', 'ar' => 'اتصل بنا'], 'contacto', 'page'),
        ]);

        $footerQuick = Menu::firstOrCreate(['slug' => 'footer_quick'], ['name' => 'Enlaces rápidos del pie de página', 'location' => 'footer_quick', 'is_active' => true]);
        $footerQuick->markets()->sync($regions);
        $this->seedMenuItems($footerQuick, [
            $this->menuItem(['es' => 'Cómo funciona', 'en' => 'How it works', 'fr' => 'Comment ça marche', 'it' => 'Come funziona', 'pt' => 'Como funciona', 'de' => 'So funktioniert es', 'zh-CN' => '工作原理', 'ar' => 'كيف تعمل المنصة'], 'como-funciona', 'page'),
            $this->menuItem(['es' => 'Módulos', 'en' => 'Modules', 'fr' => 'Modules', 'it' => 'Moduli', 'pt' => 'Módulos', 'de' => 'Module', 'zh-CN' => '模块', 'ar' => 'الوحدات'], 'modulos', 'page'),
            $this->menuItem(['es' => 'Bloques', 'en' => 'Blocks', 'fr' => 'Blocs', 'it' => 'Blocchi', 'pt' => 'Blocos', 'de' => 'Blöcke', 'zh-CN' => '区块', 'ar' => 'الوحدات والمكونات'], 'bloques', 'page'),
            $this->menuItem(['es' => 'Contacto', 'en' => 'Contact', 'fr' => 'Contact', 'it' => 'Contatto', 'pt' => 'Contacto', 'de' => 'Kontakt', 'zh-CN' => '联系', 'ar' => 'اتصل بنا'], 'contacto', 'page'),
        ]);

        $footerLegal = Menu::firstOrCreate(['slug' => 'footer_legal'], ['name' => 'Enlaces legales del pie de página', 'location' => 'footer_legal', 'is_active' => true]);
        $footerLegal->markets()->sync($regions);
        $this->seedMenuItems($footerLegal, [
            $this->menuItem(['es' => 'Política de privacidad', 'en' => 'Privacy Policy', 'fr' => 'Politique de confidentialité', 'it' => 'Informativa sulla privacy', 'pt' => 'Política de privacidade', 'de' => 'Datenschutzerklärung', 'zh-CN' => '隐私政策', 'ar' => 'سياسة الخصوصية'], 'privacidad', 'page'),
            $this->menuItem(['es' => 'Términos de servicio', 'en' => 'Terms of Service', 'fr' => 'Conditions d’utilisation', 'it' => 'Termini di servizio', 'pt' => 'Termos de serviço', 'de' => 'Nutzungsbedingungen', 'zh-CN' => '服务条款', 'ar' => 'شروط الخدمة'], 'terminos', 'page'),
        ]);
    }

    /**
     * @param  array<string, string>  $titles  locale => localized title
     * @return array{translations: array<string, string>, url: string, type: string}
     */
    protected function menuItem(array $titles, string $url, string $type): array
    {
        return ['translations' => $titles, 'url' => $url, 'type' => $type];
    }

    /**
     * @param  list<array{translations: array<string, string>, url: string, type: string}>  $items
     */
    protected function seedMenuItems(Menu $menu, array $items): void
    {
        foreach ($items as $order => $definition) {
            $item = MenuItem::firstOrCreate(
                ['menu_id' => $menu->id, 'url' => $definition['url']],
                ['type' => $definition['type'], 'order' => $order + 1, 'is_active' => true]
            );

            foreach ($definition['translations'] as $locale => $title) {
                MenuItemTranslation::updateOrCreate(
                    ['menu_item_id' => $item->id, 'locale' => $locale],
                    ['title' => $title]
                );
            }
        }
    }

    /**
     * @param  list<int>  $regions
     */
    protected function seedContactForm(array $regions): void
    {
        $form = CustomForm::firstOrCreate(['slug' => 'contacto'], ['is_active' => true]);
        $form->markets()->sync($regions);

        CustomFormTranslation::updateOrCreate(
            ['custom_form_id' => $form->id, 'locale' => 'es', 'market_id' => null],
            [
                'name' => 'Formulario de contacto',
                'description' => 'Cuéntanos qué necesitas y te responderemos en menos de 24 horas.',
                'submit_button_text' => 'Enviar mensaje',
                'success_message' => 'Gracias por escribirnos. Te responderemos en menos de 24 horas.',
            ]
        );

        CustomFormTranslation::updateOrCreate(
            ['custom_form_id' => $form->id, 'locale' => 'en', 'market_id' => null],
            [
                'name' => 'Contact form',
                'description' => 'Tell us what you need and we will reply within 24 hours.',
                'submit_button_text' => 'Send message',
                'success_message' => 'Thank you for reaching out. We will reply within 24 hours.',
            ]
        );

        $fields = [
            ['nombre', 'Nombre y apellidos', 'Tu nombre completo', 'text', true],
            ['email', 'Correo electrónico', 'tu@empresa.com', 'email', true],
            ['empresa', 'Empresa u organización', 'Nombre de tu empresa', 'text', false],
            ['mensaje', '¿En qué podemos ayudarte?', 'Detalla tu consulta', 'textarea', true],
        ];

        foreach ($fields as $order => [$key, $label, $placeholder, $type, $required]) {
            $field = FormField::firstOrCreate(
                ['custom_form_id' => $form->id, 'field_key' => $key],
                ['type' => $type, 'is_required' => $required, 'order' => $order + 1]
            );

            FormFieldTranslation::updateOrCreate(
                ['form_field_id' => $field->id, 'locale' => 'es', 'market_id' => null],
                ['label' => $label, 'placeholder' => $placeholder]
            );
        }

        // Sample leads so the inbox is meaningful from the first login.
        $leads = [
            ['nombre' => 'Marta Ruiz', 'email' => 'marta@example.com', 'empresa' => 'Estudio Norte', 'mensaje' => 'Queremos publicar en cuatro países con un solo panel.'],
            ['nombre' => 'John Carter', 'email' => 'john@example.com', 'empresa' => 'Northwind', 'mensaje' => 'Interested in connecting an AI assistant to our CMS.'],
        ];

        foreach ($leads as $index => $lead) {
            $market = Market::query()->where('is_active', true)->orderBy('id')->skip($index)->first()
                ?? Market::query()->where('is_active', true)->first();

            // The payload holds the values; idempotency is anchored on the form.
            if (FormSubmission::where('custom_form_id', $form->id)->where('ip_address', '203.0.113.'.(10 + $index))->exists()) {
                continue;
            }

            FormSubmission::create([
                'custom_form_id' => $form->id,
                'payload' => $lead,
                'locale' => $index === 0 ? 'es' : 'en',
                'market_id' => $market?->id,
                'ip_address' => '203.0.113.'.(10 + $index),
                'user_agent' => 'Mozilla/5.0 (Demo)',
            ]);
        }
    }

    /**
     * Blog: the self-explaining articles. Every post is market-assigned, so the
     * demo also proves market-scoped content resolution.
     *
     * @param  list<int>  $regions
     */
    protected function seedBlog(array $regions): void
    {
        $author = User::where('email', 'author@db-cms.io')->first()
            ?? User::where('email', 'admin@db-cms.io')->firstOrFail();

        $categories = [
            'guias' => [
                'es' => ['Guías de producto', 'Aprende a sacar partido a la plataforma paso a paso.'],
                'en' => ['Product guides', 'Learn how to get the most out of the platform, step by step.'],
            ],
            'arquitectura' => [
                'es' => ['Arquitectura', 'Decisiones técnicas detrás de dbCMS.'],
                'en' => ['Architecture', 'The engineering decisions behind dbCMS.'],
            ],
            'internacionalizacion' => [
                'es' => ['Internacionalización', 'Mercados, idiomas y contenido localizado.'],
                'en' => ['Internationalization', 'Markets, languages and localized content.'],
            ],
            'geo-ia' => [
                'es' => ['GEO y agentes de IA', 'Visibilidad en motores generativos y conexión por MCP.'],
                'en' => ['GEO & AI agents', 'Generative engine visibility and MCP connectivity.'],
            ],
        ];

        // Hierarchical taxonomy: a nested tree proves the slug-path resolution
        // (tecnología → ia-geo → modelos-llm) works across languages.
        $hierarchy = [
            'tecnologia' => [
                'es' => ['slug' => 'tecnologia', 'name' => 'Tecnología y software', 'description' => 'Ingeniería de software y arquitectura web moderna.'],
                'en' => ['slug' => 'technology', 'name' => 'Technology & Software', 'description' => 'Modern software engineering and web architecture.'],
                'children' => [
                    'ia-geo' => [
                        'es' => ['slug' => 'ia-geo', 'name' => 'Inteligencia artificial y GEO', 'description' => 'Optimización para motores generativos.'],
                        'en' => ['slug' => 'ai-geo', 'name' => 'Generative AI & GEO', 'description' => 'Optimization for generative engines.'],
                        'children' => [
                            'modelos-llm' => [
                                'es' => ['slug' => 'modelos-llm', 'name' => 'Modelos LLM y agentes', 'description' => 'Patrones de diseño de agentes y grandes modelos.'],
                                'en' => ['slug' => 'llm-models', 'name' => 'LLM Models & Agents', 'description' => 'Agent patterns and large language models.'],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->seedCategoryTree($hierarchy, null, $regions);

        $categoryModels = [];
        foreach ($categories as $slug => $titles) {
            // `categories` has no slug column: the slug lives in the translation.
            // Idempotency is anchored on the Spanish translation slug.
            $existing = CategoryTranslation::query()
                ->where('locale', 'es')
                ->where('slug', $slug)
                ->first();

            $category = $existing->category ?? Category::create(['parent_id' => null, 'is_active' => true]);
            $category->markets()->sync($regions);
            $categoryModels[$slug] = $category;

            foreach ($titles as $locale => [$name, $description]) {
                CategoryTranslation::updateOrCreate(
                    ['category_id' => $category->id, 'locale' => $locale, 'market_id' => null],
                    ['name' => $name, 'slug' => $slug, 'description' => $description]
                );
            }
        }

        foreach ($this->posts() as $definition) {
            // `posts` has no slug column: idempotency is anchored on the ES
            // translation slug, which is derived from the Spanish title.
            $anchorSlug = Str::slug($definition['translations']['es']['title']);
            $existing = PostTranslation::query()
                ->where('locale', 'es')
                ->where('slug', $anchorSlug)
                ->first();

            $post = $existing->post ?? Post::create([
                'user_id' => $author->id,
                'category_id' => $categoryModels[$definition['category']]->id,
                'reading_time_minutes' => $definition['reading_time'],
                'is_featured' => $definition['featured'],
                'is_published' => true,
                'published_at' => now(),
            ]);

            $post->markets()->sync($regions);

            foreach ($definition['translations'] as $locale => $translation) {
                PostTranslation::updateOrCreate(
                    ['post_id' => $post->id, 'locale' => $locale, 'market_id' => null],
                    [
                        'title' => $translation['title'],
                        'slug' => Str::slug($translation['title']),
                        'content' => $translation['content'],
                        'summary' => $translation['summary'],
                        'meta_title' => $translation['title'],
                        'meta_description' => $translation['summary'],
                    ]
                );
            }
        }
    }

    /**
     * Creates a nested category tree (parent → children) with per-locale slugs.
     *
     * @param  array<string, array<string, mixed>>  $tree
     * @param  list<int>  $regions
     */
    protected function seedCategoryTree(array $tree, ?int $parentId, array $regions): void
    {
        foreach ($tree as $slug => $definition) {
            $existing = CategoryTranslation::query()
                ->where('locale', 'es')
                ->where('slug', $slug)
                ->first();

            $category = $existing->category ?? Category::create([
                'parent_id' => $parentId,
                'is_active' => true,
            ]);

            $category->markets()->sync($regions);

            foreach (['es', 'en'] as $locale) {
                if (! isset($definition[$locale])) {
                    continue;
                }

                $translation = $definition[$locale];

                CategoryTranslation::updateOrCreate(
                    ['category_id' => $category->id, 'locale' => $locale, 'market_id' => null],
                    [
                        'name' => $translation['name'],
                        'slug' => $translation['slug'],
                        'description' => $translation['description'],
                    ]
                );
            }

            /** @var array<string, mixed> $children */
            $children = $definition['children'] ?? [];

            if ($children !== []) {
                $this->seedCategoryTree($children, (int) $category->id, $regions);
            }
        }
    }

    /**
     * The articles themselves: a guided tour of dbCMS.
     *
     * @return list<array{slug: string, category: string, reading_time: int, featured: bool, translations: array<string, array{title: string, summary: string, content: string}>}>
     */
    protected function posts(): array
    {
        return [
            [
                'slug' => 'que-es-dbcms',
                'category' => 'guias',
                'reading_time' => 5,
                'featured' => true,
                'translations' => [
                    'es' => [
                        'title' => 'Qué es dbCMS y por qué está pensado para varios mercados e idiomas',
                        'summary' => 'Recorrido por la plataforma: un único contenido, tantos mercados e idiomas como necesites, sin duplicar tablas ni plantillas.',
                        'content' => "## Una base de datos, muchos países\n\ndbCMS separa el **contenido** de dónde y en qué idioma se muestra. Cada página, artículo o producto existe una sola vez y se asocia a los mercados donde debe aparecer.\n\n## El modelo en tres capas\n\n1. **Entidad**: la pieza de contenido (una página, un artículo, un producto).\n2. **Traducción**: el texto de esa entidad en cada idioma.\n3. **Mercado**: la lista de mercados donde esa entidad se publica.\n\nCuando un visitante entra en `/eu/es/...` el sistema resuelve el mercado y el idioma, y sirve el contenido correcto sin consultas duplicadas.\n\n## Qué puedes hacer hoy\n\n- Crear idiomas y mercados desde el panel, sin tocar código.\n- Publicar el mismo artículo en Europa y América con el mismo slug lógico.\n- Escribir SEO por idioma y dejar que el mercado decida dónde se muestra.\n\nEste mismo blog es la demostración: cada artículo se publica en los siete mercados de ejemplo.",
                    ],
                    'en' => [
                        'title' => 'What dbCMS is and why it is built for multiple markets and languages',
                        'summary' => 'A tour of the platform: one piece of content, as many markets and languages as you need, with no duplicated tables or templates.',
                        'content' => "## One database, many countries\n\ndbCMS separates the **content** from where and in which language it is shown. Every page, article or product exists once and is linked to the markets where it should appear.\n\n## The three-layer model\n\n1. **Entity**: the content piece (a page, a post, a product).\n2. **Translation**: that entity's text in each language.\n3. **Market**: the list of markets where the entity is published.\n\nWhen a visitor lands on `/eu/en/...` the system resolves market and language, and serves the right content without duplicate queries.\n\n## What you can do today\n\n- Create languages and markets from the panel, without touching code.\n- Publish the same article in Europe and America with the same logical slug.\n- Write SEO per language and let the market decide where it is shown.\n\nThis very blog is the demo: each article is published across the seven sample markets.",
                    ],
                    'fr' => [
                        'title' => "Qu'est-ce que dbCMS et pourquoi il est pensé pour plusieurs marchés et langues",
                        'summary' => 'Un contenu unique, autant de marchés et de langues que nécessaire, sans dupliquer les tables ni les modèles.',
                        'content' => "## Une base de données, plusieurs pays\n\ndbCMS sépare le **contenu** de l'endroit et de la langue où il est affiché. Chaque page, article ou produit existe une seule fois et est associé aux marchés où il doit apparaître.\n\n## Le modèle en trois couches\n\n1. **Entité** : la pièce de contenu.\n2. **Traduction** : le texte dans chaque langue.\n3. **Marché** : la liste des marchés où l'entité est publiée.\n\nCe blog est la démonstration : chaque article est publié sur les sept marchés d'exemple.",
                    ],
                    'de' => [
                        'title' => 'Was dbCMS ist und warum es für mehrere Märkte und Sprachen gebaut ist',
                        'summary' => 'Ein Inhalt, so viele Märkte und Sprachen wie nötig – ohne doppelte Tabellen oder Vorlagen.',
                        'content' => "## Eine Datenbank, viele Länder\n\ndbCMS trennt den **Inhalt** davon, wo und in welcher Sprache er angezeigt wird. Jede Seite, jeder Artikel und jedes Produkt existiert einmal und wird den Märkten zugeordnet.\n\n## Das Drei-Schichten-Modell\n\n1. **Entität**: das Inhaltselement.\n2. **Übersetzung**: der Text in jeder Sprache.\n3. **Markt**: die Liste der Märkte, in denen veröffentlicht wird.\n\nDieser Blog ist die Demonstration: jeder Artikel erscheint in den sieben Beispielmärkten.",
                    ],
                ],
            ],
            [
                'slug' => 'arquitectura-limpia-servicios',
                'category' => 'arquitectura',
                'reading_time' => 6,
                'featured' => true,
                'translations' => [
                    'es' => [
                        'title' => 'Arquitectura limpia: controladores finos y servicios de dominio',
                        'summary' => 'Cómo está organizado el código para que cada pieza tenga una única responsabilidad y sea fácil de auditar.',
                        'content' => "## Controladores finos, servicios gruesos\n\nCada acción del panel es un **controlador invocable** que valida la petición y delega en un **servicio de dominio**. La lógica de negocio nunca vive en el controlador.\n\n## Módulos desacoplados\n\nLos módulos (blog, tienda, galería, formularios…) declaran su ciclo de vida en un `module.json` y se registran en el contenedor. Activar o desactivar un módulo no rompe el resto del sistema.\n\n## Contratos, no acoplamiento\n\nLas capas se comunican por interfaces (`SectionLayout`, `LocalizationService`, `ModuleSettingsService`). Cambiar una implementación no obliga a tocar sus consumidores.\n\n## Por qué importa\n\nUna buena arquitectura no es un lujo académico: es lo que permite añadir un mercado, un idioma o un módulo sin miedo a romper lo que ya funciona.",
                    ],
                    'en' => [
                        'title' => 'Clean architecture: thin controllers and fat domain services',
                        'summary' => 'How the code is organised so every piece has a single responsibility and stays easy to audit.',
                        'content' => "## Thin controllers, fat services\n\nEvery panel action is an **invokable controller** that validates the request and delegates to a **domain service**. Business logic never lives in the controller.\n\n## Decoupled modules\n\nModules (blog, shop, gallery, forms…) declare their lifecycle in a `module.json` and register themselves in the container. Enabling or disabling a module never breaks the rest of the system.\n\n## Contracts, not coupling\n\nLayers talk to each other through interfaces (`SectionLayout`, `LocalizationService`, `ModuleSettingsService`). Swapping an implementation never forces changes in its consumers.\n\n## Why it matters\n\nGood architecture is not academic: it is what lets you add a market, a language or a module without breaking what already works.",
                    ],
                ],
            ],
            [
                'slug' => 'contenido-por-mercado-e-idioma',
                'category' => 'internacionalizacion',
                'reading_time' => 5,
                'featured' => false,
                'translations' => [
                    'es' => [
                        'title' => 'Cómo funciona el contenido por mercado y por idioma',
                        'summary' => 'La diferencia entre dónde se muestra un contenido y en qué idioma se lee, y por qué no debes mezclarlas.',
                        'content' => "## Dos dimensiones independientes\n\n**Idioma** responde a *cómo se lee* el contenido. **Mercado** responde a *dónde* se muestra. Son ejes separados.\n\n## El modelo de publicación\n\n- Un contenido **sin mercados asignados** es global: se muestra en todos.\n- Un contenido **asignado a mercados** solo aparece en esos.\n\n## SEO por idioma\n\nEl título SEO, la descripción y el tipo de schema se editan por idioma, igual que el título y el slug. El mercado no crea variantes de SEO: decide visibilidad.\n\n## Fallback predecible\n\nSi un mercado no tiene un idioma, cae al idioma por defecto del mercado. Así el visitante siempre ve algo coherente y tú no duplicas contenido.",
                    ],
                    'en' => [
                        'title' => 'How per-market and per-language content works',
                        'summary' => 'The difference between where content is shown and in which language it is read, and why you must not mix them.',
                        'content' => "## Two independent dimensions\n\n**Language** answers *how* content is read. **Market** answers *where* it is shown. They are separate axes.\n\n## The publishing model\n\n- Content with **no markets assigned** is global: shown everywhere.\n- Content **assigned to markets** appears only in those.\n\n## SEO per language\n\nThe SEO title, description and schema type are edited per language, just like the title and the slug. The market does not create SEO variants: it decides visibility.\n\n## Predictable fallback\n\nIf a market does not have a language, it falls back to the market's default language. Visitors always see something coherent and you never duplicate content.",
                    ],
                ],
            ],
            [
                'slug' => 'geo-y-agentes-mcp',
                'category' => 'geo-ia',
                'reading_time' => 6,
                'featured' => false,
                'translations' => [
                    'es' => [
                        'title' => 'GEO y agentes: cómo conectar tu CMS por MCP',
                        'summary' => 'Publica contenido legible por motores generativos y permite que asistentes de IA trabajen sobre tu CMS con permisos acotados.',
                        'content' => "## Visibilidad en buscadores generativos\n\nEl motor **GEO** genera `/llms.txt` y `/llms-full.txt` con el texto útil de cada página, artículo y producto, para que ChatGPT, Perplexity o Gemini puedan citarte con precisión.\n\n## Conexión segura por MCP\n\nEl **Model Context Protocol** expone el panel como capacidades con OAuth2 y scopes. Un asistente solo puede hacer lo que su token autoriza.\n\n## Trazabilidad\n\nCada llamada de un agente queda registrada en la auditoría: quién, qué y cuándo. Puedes revocar un token al instante.\n\n## Pruébalo\n\nDesde el panel puedes emitir un token personal y conectar un cliente MCP en minutos.",
                    ],
                    'en' => [
                        'title' => 'GEO and agents: connecting your CMS over MCP',
                        'summary' => 'Publish content that generative engines can read and let AI assistants work on your CMS with scoped permissions.',
                        'content' => "## Visibility in generative search\n\nThe **GEO** engine generates `/llms.txt` and `/llms-full.txt` with the useful text of every page, article and product, so ChatGPT, Perplexity or Gemini can cite you accurately.\n\n## Secure connection over MCP\n\nThe **Model Context Protocol** exposes the panel as capabilities with OAuth2 and scopes. An assistant can only do what its token allows.\n\n## Traceability\n\nEvery agent call is recorded in the audit trail: who, what and when. You can revoke a token instantly.\n\n## Try it\n\nFrom the panel you can issue a personal token and connect an MCP client in minutes.",
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<int>  $regions
     */
    protected function seedGallery(array $regions): void
    {
        $gallery = Gallery::firstOrCreate(
            ['slug' => 'recorrido-plataforma'],
            ['title' => 'Recorrido por la plataforma', 'description' => 'Capturas de la arquitectura y del flujo de publicación.', 'is_active' => true]
        );
        $gallery->markets()->sync($regions);

        GalleryTranslation::updateOrCreate(
            ['gallery_id' => $gallery->id, 'locale' => 'es', 'market_id' => null],
            [
                'title' => 'Recorrido por la plataforma',
                'slug' => 'recorrido-plataforma',
                'description' => 'Capturas de la arquitectura y del flujo de publicación multi-mercado.',
                'meta_title' => 'Recorrido por la plataforma',
                'meta_description' => 'Capturas de la arquitectura y del flujo de publicación multi-mercado.',
            ]
        );

        GalleryTranslation::updateOrCreate(
            ['gallery_id' => $gallery->id, 'locale' => 'en', 'market_id' => null],
            [
                'title' => 'A tour of the platform',
                'slug' => 'platform-tour',
                'description' => 'Screenshots of the architecture and the multi-market publishing flow.',
                'meta_title' => 'A tour of the platform',
                'meta_description' => 'Screenshots of the architecture and the multi-market publishing flow.',
            ]
        );

        $images = [
            ['https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'Panel de secciones', 'Editor visual de secciones y columnas'],
            ['https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1000&q=80', 'Matriz de mercados', 'Un contenido, varios mercados e idiomas'],
            ['https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1000&q=80', 'Conexión MCP', 'Agentes de IA con permisos acotados'],
        ];

        foreach ($images as $index => [$url, $title, $caption]) {
            GalleryItem::updateOrCreate(
                ['gallery_id' => $gallery->id, 'order' => $index + 1],
                ['image_url' => $url, 'title' => $title, 'caption' => $caption]
            );
        }
    }

    /**
     * @param  list<int>  $regions
     */
    protected function seedShop(array $regions): void
    {
        app(ModuleSettingsServiceInterface::class)->set('shop', 'shop_mode', 'full_shop');
        app(ModuleSettingsServiceInterface::class)->set('shop', 'catalog_show_prices', true);
        app(ModuleSettingsServiceInterface::class)->set('shop', 'currency', 'EUR');

        // Storefront catalogue: one category per product family.
        $catalogue = [
            ['modulos', 'modulos', 'Módulos y licencias', 'Modules & licenses', '🧩', 'Licencias y módulos del producto.'],
            ['servidores-edge', 'edge-servers', 'Servidores y computación perimetral', 'Edge servers & compute', '🖥️', 'Hardware perimetral y cómputo de baja latencia.'],
            ['redes-cuanticas', 'quantum-networking', 'Redes Cuánticas', 'Quantum Networking', '🛰️', 'Infraestructura de red de nueva generación.'],
        ];

        foreach ($catalogue as $index => [$slugEs, $slugEn, $nameEs, $nameEn, $icon, $description]) {
            $existing = ProductCategoryTranslation::query()
                ->where('locale', 'es')
                ->where('slug', $slugEs)
                ->first();

            $model = $existing->category ?? ProductCategory::create([
                'icon' => $icon,
                'is_active' => true,
                'order' => $index + 1,
            ]);
            $model->markets()->sync($regions);

            ProductCategoryTranslation::updateOrCreate(
                ['category_id' => $model->id, 'locale' => 'es'],
                ['name' => $nameEs, 'slug' => $slugEs, 'description' => $description]
            );
            ProductCategoryTranslation::updateOrCreate(
                ['category_id' => $model->id, 'locale' => 'en'],
                ['name' => $nameEn, 'slug' => $slugEn, 'description' => $description]
            );
        }

        // Every demo product belongs to the "modulos" family.
        $category = ProductCategory::whereHas(
            'translations',
            fn ($q) => $q->where('locale', 'es')->where('slug', 'modulos')
        )->firstOrFail();

        $products = [
            [
                'sku' => 'PRD-EDGE-1000',
                'price' => 1299.00,
                'compare_price' => 1499.00,
                'featured' => true,
                'es' => [
                    'name' => 'Servidor Edge Cyber-KV 10G',
                    'slug' => 'servidor-edge-cyber-kv-10g',
                    'summary' => 'Servidor perimetral para caché distribuida y cómputo de baja latencia.',
                    'description' => "## Cómputo perimetral de alto rendimiento\n\nEl **Servidor Edge Cyber-KV 10G** está diseñado para servir contenido en el borde de la red.\n\n### Especificaciones técnicas\n\n- CPU: **64-Core ARM Neoverse N2**\n- Memoria: 256 GB DDR5 ECC\n- Almacenamiento: 8 TB NVMe Gen4\n- Red: 2 x 10 GbE SFP+\n\nIdeal para despliegues multi-mercado con requisitos de latencia estrictos.",
                ],
                'en' => [
                    'name' => 'Cyber-KV 10G Edge Server',
                    'slug' => 'cyber-kv-10g-edge-server',
                    'summary' => 'Edge server for distributed cache and low-latency compute.',
                    'description' => "## High-performance edge compute\n\nThe **Cyber-KV 10G Edge Server** is built to serve content at the edge of the network.\n\n### Technical specifications\n\n- CPU: **64-Core ARM Neoverse N2**\n- Memory: 256 GB DDR5 ECC\n- Storage: 8 TB NVMe Gen4\n- Network: 2 x 10 GbE SFP+\n\nIdeal for multi-market deployments with strict latency requirements.",
                ],
            ],
            [
                'sku' => 'DBCMS-CORE',
                'price' => 0.0,
                'compare_price' => null,
                'featured' => false,
                'es' => ['name' => 'dbCMS Core (incluido)', 'summary' => 'Motor de páginas, blogs, galerías, formularios, tienda y búsqueda.', 'slug' => 'dbcms-core', 'description' => 'Motor de páginas, blogs, galerías, formularios, tienda y búsqueda.'],
                'en' => ['name' => 'dbCMS Core (included)', 'summary' => 'Pages, blog, gallery, forms, shop and search engines.', 'slug' => 'dbcms-core', 'description' => 'Pages, blog, gallery, forms, shop and search engines.'],
            ],
            [
                'sku' => 'DBCMS-MCP',
                'price' => 49.0,
                'compare_price' => null,
                'featured' => false,
                'es' => ['name' => 'Conector MCP', 'summary' => 'Conecta asistentes de IA a tu CMS con permisos acotados.', 'slug' => 'conector-mcp', 'description' => 'Conecta asistentes de IA a tu CMS con permisos acotados.'],
                'en' => ['name' => 'MCP connector', 'summary' => 'Connect AI assistants to your CMS with scoped permissions.', 'slug' => 'mcp-connector', 'description' => 'Connect AI assistants to your CMS with scoped permissions.'],
            ],
        ];

        foreach ($products as $definition) {
            $product = Product::firstOrCreate(
                ['sku' => $definition['sku']],
                [
                    'category_id' => $category->id,
                    'price' => $definition['price'],
                    'compare_price' => $definition['compare_price'],
                    'is_featured' => $definition['featured'],
                    'is_active' => true,
                    'stock_quantity' => 100,
                ]
            );

            // Per-market pricing: the pivot carries the local price so the same
            // product shows EUR in Europe and USD in the United States.
            $pricing = [];
            foreach (Market::query()->where('is_active', true)->get() as $market) {
                $pricing[$market->id] = [
                    'is_active' => true,
                    'custom_price' => $this->marketPrice($definition['price'], (string) $market->currency),
                ];
            }
            $product->markets()->sync($pricing);

            foreach (['es', 'en'] as $locale) {
                ProductTranslation::updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    [
                        'name' => $definition[$locale]['name'],
                        'slug' => $definition[$locale]['slug'],
                        'summary' => $definition[$locale]['summary'],
                        'description' => $definition[$locale]['description'],
                        'meta_title' => $definition[$locale]['name'],
                        'meta_description' => $definition[$locale]['summary'],
                        'schema_type' => 'Product',
                    ]
                );
            }
        }
    }

    /**
     * Converts the base EUR price into a market currency so the multi-market
     * demo shows realistic local pricing.
     */
    protected function marketPrice(float $baseEur, string $currency): float
    {
        // Demo prices are rounded to the local currency for a clean catalogue.
        $rates = ['EUR' => 1.0, 'USD' => 1.0770, 'GBP' => 0.85, 'MXN' => 19.5, 'COP' => 4300.0, 'CNY' => 7.8, 'AED' => 3.95];

        $rate = $rates[$currency] ?? 1.0;
        $price = round($baseEur * $rate, 2);

        // Prices are rounded to the nearest whole unit for a clean catalogue.
        return in_array($currency, ['USD', 'GBP', 'MXN', 'COP', 'CNY', 'AED'], true) ? round($price) : $price;
    }

    /**
     * The narrative pages: home, how it works, modules, about and legal.
     *
     * @param  list<int>  $regions
     */
    protected function seedPages(array $regions, string $brand): void
    {
        foreach ($this->pages($brand) as $definition) {
            // `pages` has no slug column: idempotency is anchored on the default
            // language translation, so several `default`-template pages coexist.
            $anchorSlug = $definition['translations']['es']['slug'] ?? $definition['translations']['en']['slug'];
            $existing = PageTranslation::query()
                ->where('locale', 'es')
                ->where('slug', $anchorSlug)
                ->first();

            $page = $existing->page ?? Page::create([
                'template' => $definition['template'],
                'layout' => 'app',
                'is_published' => true,
                'published_at' => now(),
            ]);
            $page->markets()->sync($definition['markets'] ?? $regions);

            foreach ($definition['translations'] as $locale => $translation) {
                PageTranslation::updateOrCreate(
                    ['page_id' => $page->id, 'locale' => $locale, 'market_id' => null],
                    [
                        'title' => $translation['title'],
                        'slug' => $translation['slug'],
                        'meta_title' => $translation['meta_title'],
                        'meta_description' => $translation['meta_description'],
                        'schema_type' => 'WebPage',
                    ]
                );
            }

            foreach ($definition['sections'] as $order => $section) {
                $pageSection = PageSection::firstOrCreate(
                    ['page_id' => $page->id, 'order' => $order + 1],
                    [
                        'type' => $section['type'],
                        'styling_json' => $section['styling'] ?? ['container_style' => 'cyber-card'],
                        'is_active' => true,
                    ]
                );

                foreach ($section['translations'] as $locale => $content) {
                    SectionTranslation::updateOrCreate(
                        ['page_section_id' => $pageSection->id, 'locale' => $locale, 'market_id' => null],
                        ['content_json' => $content]
                    );
                }
            }
        }
    }

    /**
     * Showcase sections (part 1/2): hero, kpi, features, columns, pricing, faq,
     * testimonials, carousel and before/after — every block fully translated.
     *
     * @return list<array{type: string, styling?: array<string, mixed>, translations: array<string, array<string, mixed>>}>
     */
    protected function showcaseSectionsA(): array
    {
        $heroContent = [
            'es' => 'Esta página está construida con los mismos bloques que editas desde el panel: cada sección es un bloque configurable — columnas, fondos, animaciones y datos incluidos.',
            'en' => 'This page is built with the very same blocks you edit from the backoffice: every section is a configurable block — columns, backgrounds, animations and data included.',
            'fr' => 'Cette page est construite avec les mêmes blocs que vous éditez depuis le backoffice : chaque section est un bloc configurable — colonnes, arrière-plans, animations et données comprises.',
            'it' => 'Questa pagina è costruita con gli stessi blocchi che modifichi dal backoffice: ogni sezione è un blocco configurabile — colonne, sfondi, animazioni e dati inclusi.',
            'pt' => 'Esta página foi construída com os mesmos blocos que editas no backoffice: cada secção é um bloco configurável — colunas, fundos, animações e dados incluídos.',
            'de' => 'Diese Seite wurde mit denselben Blöcken gebaut, die du im Backoffice bearbeitest: Jede Sektion ist ein konfigurierbarer Block — Spalten, Hintergründe, Animationen und Daten inklusive.',
            'zh-CN' => '本页正是用你在后台编辑的同一套区块搭建而成：每个版块都是一个可配置区块——栏目、背景、动画与数据，一应俱全。',
            'ar' => 'بُنيت هذه الصفحة بنفس الوحدات التي تحررها من لوحة التحكم: كل قسم وحدة قابلة للتخصيص — أعمدة وخلفيات وحركات وبيانات.',
        ];

        $kpiItems = [
            'es' => [['8', 'Idiomas activos'], ['8', 'Mercados en vivo'], ['18+', 'Bloques listos'], ['100%', 'Suite en verde']],
            'en' => [['8', 'Active languages'], ['8', 'Live markets'], ['18+', 'Ready-made blocks'], ['100%', 'Test suite green']],
            'fr' => [['8', 'Langues actives'], ['8', 'Marchés actifs'], ['18+', 'Blocs prêts'], ['100%', 'Suite de tests verte']],
            'it' => [['8', 'Lingue attive'], ['8', 'Mercati attivi'], ['18+', 'Blocchi pronti'], ['100%', 'Suite di test verde']],
            'pt' => [['8', 'Idiomas ativos'], ['8', 'Mercados ativos'], ['18+', 'Blocos prontos'], ['100%', 'Suíte de testes verde']],
            'de' => [['8', 'Aktive Sprachen'], ['8', 'Aktive Märkte'], ['18+', 'Einsatzbereite Blöcke'], ['100%', 'Test-Suite grün']],
            'zh-CN' => [['8', '活跃语言'], ['8', '活跃市场'], ['18+', '可用区块'], ['100%', '测试全部通过']],
            'ar' => [['8', 'لغات نشطة'], ['8', 'أسواق نشطة'], ['18+', 'وحدات جاهزة'], ['100%', 'اختبارات خضراء']],
        ];

        $featureItems = [
            'es' => [
                ['icon' => '⚙️', 'title' => 'Páginas por secciones', 'description' => 'Columnas, fondos, animaciones y divisores de forma.'],
                ['icon' => '📝', 'title' => 'Blog multilingüe', 'description' => 'Taxonomías, comentarios y URLs amigables.'],
                ['icon' => '🛒', 'title' => 'Tienda por mercado', 'description' => 'Precios y stock distintos según el mercado.'],
                ['icon' => '🖼️', 'title' => 'Galerías localizadas', 'description' => 'Álbumes con títulos y descripciones por idioma.'],
                ['icon' => '📬', 'title' => 'Formularios antispam', 'description' => 'Honeypot invisible y campos personalizados.'],
                ['icon' => '🔎', 'title' => 'Búsqueda amigable', 'description' => 'Filtros con URL legible y facetas por entidad.'],
            ],
            'en' => [
                ['icon' => '⚙️', 'title' => 'Section-based pages', 'description' => 'Columns, backgrounds, animations and shape dividers.'],
                ['icon' => '📝', 'title' => 'Multilingual blog', 'description' => 'Taxonomies, comments and friendly URLs.'],
                ['icon' => '🛒', 'title' => 'Per-market shop', 'description' => 'Different prices and stock for each market.'],
                ['icon' => '🖼️', 'title' => 'Localized galleries', 'description' => 'Albums with per-language titles and captions.'],
                ['icon' => '📬', 'title' => 'Antispam forms', 'description' => 'Invisible honeypot and custom fields.'],
                ['icon' => '🔎', 'title' => 'Friendly search', 'description' => 'Readable filter URLs with per-entity facets.'],
            ],
            'fr' => [
                ['icon' => '⚙️', 'title' => 'Pages par sections', 'description' => 'Colonnes, arrière-plans, animations et séparateurs de forme.'],
                ['icon' => '📝', 'title' => 'Blog multilingue', 'description' => 'Taxonomies, commentaires et URL conviviales.'],
                ['icon' => '🛒', 'title' => 'Boutique par marché', 'description' => 'Prix et stock différents selon le marché.'],
                ['icon' => '🖼️', 'title' => 'Galeries localisées', 'description' => 'Albums avec titres et légendes par langue.'],
                ['icon' => '📬', 'title' => 'Formulaires antispam', 'description' => 'Honeypot invisible et champs personnalisés.'],
                ['icon' => '🔎', 'title' => 'Recherche conviviale', 'description' => 'URL de filtres lisibles avec facettes par entité.'],
            ],
            'it' => [
                ['icon' => '⚙️', 'title' => 'Pagine a sezioni', 'description' => 'Colonne, sfondi, animazioni e divisori di forma.'],
                ['icon' => '📝', 'title' => 'Blog multilingue', 'description' => 'Tassonomie, commenti e URL amichevoli.'],
                ['icon' => '🛒', 'title' => 'Negozio per mercato', 'description' => 'Prezzi e scorte diversi per ogni mercato.'],
                ['icon' => '🖼️', 'title' => 'Gallerie localizzate', 'description' => 'Album con titoli e didascalie per lingua.'],
                ['icon' => '📬', 'title' => 'Moduli antispam', 'description' => 'Honeypot invisibile e campi personalizzati.'],
                ['icon' => '🔎', 'title' => 'Ricerca amichevole', 'description' => 'URL di filtro leggibili con facet per entità.'],
            ],
            'pt' => [
                ['icon' => '⚙️', 'title' => 'Páginas por secções', 'description' => 'Colunas, fundos, animações e divisores de forma.'],
                ['icon' => '📝', 'title' => 'Blogue multilingue', 'description' => 'Taxonomias, comentários e URLs amigáveis.'],
                ['icon' => '🛒', 'title' => 'Loja por mercado', 'description' => 'Preços e stock diferentes por mercado.'],
                ['icon' => '🖼️', 'title' => 'Galerias localizadas', 'description' => 'Álbuns com títulos e legendas por idioma.'],
                ['icon' => '📬', 'title' => 'Formulários antispam', 'description' => 'Honeypot invisível e campos personalizados.'],
                ['icon' => '🔎', 'title' => 'Pesquisa amigável', 'description' => 'URLs de filtros legíveis com facetas por entidade.'],
            ],
            'de' => [
                ['icon' => '⚙️', 'title' => 'Seiten aus Sektionen', 'description' => 'Spalten, Hintergründe, Animationen und Formteiler.'],
                ['icon' => '📝', 'title' => 'Mehrsprachiger Blog', 'description' => 'Taxonomien, Kommentare und sprechende URLs.'],
                ['icon' => '🛒', 'title' => 'Shop pro Markt', 'description' => 'Eigene Preise und Bestände je Markt.'],
                ['icon' => '🖼️', 'title' => 'Lokalisierte Galerien', 'description' => 'Alben mit Titeln und Bildunterschriften pro Sprache.'],
                ['icon' => '📬', 'title' => 'Antispam-Formulare', 'description' => 'Unsichtbares Honeypot und eigene Felder.'],
                ['icon' => '🔎', 'title' => 'Freundliche Suche', 'description' => 'Lesbare Filter-URLs mit Facetten pro Entität.'],
            ],
            'zh-CN' => [
                ['icon' => '⚙️', 'title' => '分区块页面', 'description' => '栏目、背景、动画与形状分隔线。'],
                ['icon' => '📝', 'title' => '多语言博客', 'description' => '分类、评论与友好链接。'],
                ['icon' => '🛒', 'title' => '按市场商店', 'description' => '不同市场拥有独立价格与库存。'],
                ['icon' => '🖼️', 'title' => '本地化图库', 'description' => '相册标题与说明按语言区分。'],
                ['icon' => '📬', 'title' => '防垃圾表单', 'description' => '隐形蜜罐与自定义字段。'],
                ['icon' => '🔎', 'title' => '友好搜索', 'description' => '可读的筛选链接与实体分面。'],
            ],
            'ar' => [
                ['icon' => '⚙️', 'title' => 'صفحات من أقسام', 'description' => 'أعمدة وخلفيات وحركات وفواصل أشكال.'],
                ['icon' => '📝', 'title' => 'مدونة متعددة اللغات', 'description' => 'تصنيفات وتعليقات وروابط صديقة.'],
                ['icon' => '🛒', 'title' => 'متجر لكل سوق', 'description' => 'أسعار ومخزون مختلفان حسب السوق.'],
                ['icon' => '🖼️', 'title' => 'معارض مترجمة', 'description' => 'ألبومات بعناوين وشروحات لكل لغة.'],
                ['icon' => '📬', 'title' => 'نماذج مضادة للبريد المزعج', 'description' => 'مصيدة غير مرئية وحقول مخصصة.'],
                ['icon' => '🔎', 'title' => 'بحث بروابط صديقة', 'description' => 'روابط تصفية مقروءة مع فئات لكل كيان.'],
            ],
        ];

        $pricingItems = [
            'es' => [
                ['name' => 'Starter', 'price' => '29€/mes', 'period' => 'Para validar tu primera idea', 'features' => ['1 mercado activo', '2 idiomas', 'Hasta 10 páginas', 'Soporte por email'], 'cta_text' => 'Elegir Starter', 'cta_url' => '/contacto'],
                ['name' => 'Professional', 'price' => '99€/mes', 'period' => 'Para crecer en varios mercados', 'features' => ['8 mercados y 8 idiomas', 'Módulos ilimitados', 'CDN perimetral incluido', 'Soporte prioritario'], 'cta_text' => 'Elegir Professional', 'cta_url' => '/contacto'],
                ['name' => 'Enterprise', 'price' => 'A medida', 'period' => 'Para plataformas críticas', 'features' => ['SLA garantizado', 'Multi-tenant y SSO', 'MCP dedicado', 'Onboarding asistido'], 'cta_text' => 'Hablar con ventas', 'cta_url' => '/contacto'],
            ],
            'en' => [
                ['name' => 'Starter', 'price' => '€29/mo', 'period' => 'To validate your first idea', 'features' => ['1 active market', '2 languages', 'Up to 10 pages', 'Email support'], 'cta_text' => 'Choose Starter', 'cta_url' => '/contacto'],
                ['name' => 'Professional', 'price' => '€99/mo', 'period' => 'To grow across markets', 'features' => ['8 markets and 8 languages', 'Unlimited modules', 'Edge CDN included', 'Priority support'], 'cta_text' => 'Choose Professional', 'cta_url' => '/contacto'],
                ['name' => 'Enterprise', 'price' => 'Custom', 'period' => 'For mission-critical platforms', 'features' => ['Guaranteed SLA', 'Multi-tenant and SSO', 'Dedicated MCP', 'Guided onboarding'], 'cta_text' => 'Talk to sales', 'cta_url' => '/contacto'],
            ],
            'fr' => [
                ['name' => 'Starter', 'price' => '29€/mois', 'period' => 'Pour valider votre première idée', 'features' => ['1 marché actif', '2 langues', 'Jusqu’à 10 pages', 'Support par email'], 'cta_text' => 'Choisir Starter', 'cta_url' => '/contacto'],
                ['name' => 'Professional', 'price' => '99€/mois', 'period' => 'Pour grandir sur plusieurs marchés', 'features' => ['8 marchés et 8 langues', 'Modules illimités', 'CDN périphérique inclus', 'Support prioritaire'], 'cta_text' => 'Choisir Professional', 'cta_url' => '/contacto'],
                ['name' => 'Enterprise', 'price' => 'Sur mesure', 'period' => 'Pour les plateformes critiques', 'features' => ['SLA garanti', 'Multi-tenant et SSO', 'MCP dédié', 'Onboarding assisté'], 'cta_text' => 'Contacter les ventes', 'cta_url' => '/contacto'],
            ],
            'it' => [
                ['name' => 'Starter', 'price' => '29€/mese', 'period' => 'Per validare la tua prima idea', 'features' => ['1 mercato attivo', '2 lingue', 'Fino a 10 pagine', 'Supporto via email'], 'cta_text' => 'Scegli Starter', 'cta_url' => '/contacto'],
                ['name' => 'Professional', 'price' => '99€/mese', 'period' => 'Per crescere su più mercati', 'features' => ['8 mercati e 8 lingue', 'Moduli illimitati', 'CDN perimetrale incluso', 'Supporto prioritario'], 'cta_text' => 'Scegli Professional', 'cta_url' => '/contacto'],
                ['name' => 'Enterprise', 'price' => 'Su misura', 'period' => 'Per piattaforme critiche', 'features' => ['SLA garantito', 'Multi-tenant e SSO', 'MCP dedicato', 'Onboarding assistito'], 'cta_text' => 'Parla con le vendite', 'cta_url' => '/contacto'],
            ],
            'pt' => [
                ['name' => 'Starter', 'price' => '29€/mês', 'period' => 'Para validar a tua primeira ideia', 'features' => ['1 mercado ativo', '2 idiomas', 'Até 10 páginas', 'Suporte por email'], 'cta_text' => 'Escolher Starter', 'cta_url' => '/contacto'],
                ['name' => 'Professional', 'price' => '99€/mês', 'period' => 'Para crescer em vários mercados', 'features' => ['8 mercados e 8 idiomas', 'Módulos ilimitados', 'CDN periférico incluído', 'Suporte prioritário'], 'cta_text' => 'Escolher Professional', 'cta_url' => '/contacto'],
                ['name' => 'Enterprise', 'price' => 'Sob medida', 'period' => 'Para plataformas críticas', 'features' => ['SLA garantido', 'Multi-tenant e SSO', 'MCP dedicado', 'Onboarding assistido'], 'cta_text' => 'Falar com vendas', 'cta_url' => '/contacto'],
            ],
            'de' => [
                ['name' => 'Starter', 'price' => '29€/Monat', 'period' => 'Für die erste Idee', 'features' => ['1 aktiver Markt', '2 Sprachen', 'Bis zu 10 Seiten', 'Support per E-Mail'], 'cta_text' => 'Starter wählen', 'cta_url' => '/contacto'],
                ['name' => 'Professional', 'price' => '99€/Monat', 'period' => 'Für Wachstum über mehrere Märkte', 'features' => ['8 Märkte und 8 Sprachen', 'Unbegrenzte Module', 'Edge-CDN inklusive', 'Prioritäts-Support'], 'cta_text' => 'Professional wählen', 'cta_url' => '/contacto'],
                ['name' => 'Enterprise', 'price' => 'Individuell', 'period' => 'Für geschäftskritische Plattformen', 'features' => ['Garantiertes SLA', 'Multi-Tenant und SSO', 'Dediziertes MCP', 'Begleitetes Onboarding'], 'cta_text' => 'Vertrieb kontaktieren', 'cta_url' => '/contacto'],
            ],
            'zh-CN' => [
                ['name' => '入门版', 'price' => '€29/月', 'period' => '验证你的第一个想法', 'features' => ['1 个活跃市场', '2 种语言', '最多 10 个页面', '邮件支持'], 'cta_text' => '选择入门版', 'cta_url' => '/contacto'],
                ['name' => '专业版', 'price' => '€99/月', 'period' => '在多个市场增长', 'features' => ['8 个市场与 8 种语言', '模块不限量', '含边缘 CDN', '优先支持'], 'cta_text' => '选择专业版', 'cta_url' => '/contacto'],
                ['name' => '企业版', 'price' => '定制', 'period' => '面向关键业务平台', 'features' => ['SLA 保障', '多租户与 SSO', '专属 MCP', '协助上线'], 'cta_text' => '联系销售', 'cta_url' => '/contacto'],
            ],
            'ar' => [
                ['name' => 'الأساسية', 'price' => '€29/شهر', 'period' => 'للتحقق من فكرتك الأولى', 'features' => ['سوق واحد نشط', 'لغتان', 'حتى 10 صفحات', 'دعم عبر البريد'], 'cta_text' => 'اختر الأساسية', 'cta_url' => '/contacto'],
                ['name' => 'الاحترافية', 'price' => '€99/شهر', 'period' => 'للنمو عبر أسواق متعددة', 'features' => ['8 أسواق و8 لغات', 'وحدات غير محدودة', 'شبكة توصيل طرفية', 'دعم ذو أولوية'], 'cta_text' => 'اختر الاحترافية', 'cta_url' => '/contacto'],
                ['name' => 'المؤسسات', 'price' => 'حسب الطلب', 'period' => 'للمنصات الحرجة', 'features' => ['اتفاقية مستوى خدمة مضمونة', 'تعدد المستأجرين وSSO', 'MCP مخصص', 'تهيئة بمساعدة الفريق'], 'cta_text' => 'تحدث مع المبيعات', 'cta_url' => '/contacto'],
            ],
        ];

        return [
            [
                'type' => 'hero',
                'styling' => ['is_fullwidth' => true, 'animation' => 'fadeInUp', 'animation_delay' => '0.05s', 'divider_bottom' => ['type' => 'waves', 'height' => 60]],
                'translations' => [
                    'es' => ['badge' => 'DEMO EN VIVO', 'title' => 'Todos los bloques del CMS, en acción', 'content' => $heroContent['es'], 'cta_text' => 'Ver los módulos', 'cta_url' => '/modulos', 'secondary_cta_text' => 'Cómo funciona', 'secondary_cta_url' => '/como-funciona', 'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80'],
                    'en' => ['badge' => 'LIVE DEMO', 'title' => 'Every CMS block, in action', 'content' => $heroContent['en'], 'cta_text' => 'Explore the modules', 'cta_url' => '/modulos', 'secondary_cta_text' => 'How it works', 'secondary_cta_url' => '/como-funciona', 'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80'],
                    'fr' => ['badge' => 'DÉMO EN DIRECT', 'title' => 'Tous les blocs du CMS, en action', 'content' => $heroContent['fr'], 'cta_text' => 'Voir les modules', 'cta_url' => '/modulos', 'secondary_cta_text' => 'Comment ça marche', 'secondary_cta_url' => '/como-funciona', 'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80'],
                    'it' => ['badge' => 'DEMO DAL VIVO', 'title' => 'Tutti i blocchi del CMS, in azione', 'content' => $heroContent['it'], 'cta_text' => 'Scopri i moduli', 'cta_url' => '/modulos', 'secondary_cta_text' => 'Come funziona', 'secondary_cta_url' => '/como-funciona', 'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80'],
                    'pt' => ['badge' => 'DEMONSTRAÇÃO AO VIVO', 'title' => 'Todos os blocos do CMS, em ação', 'content' => $heroContent['pt'], 'cta_text' => 'Ver os módulos', 'cta_url' => '/modulos', 'secondary_cta_text' => 'Como funciona', 'secondary_cta_url' => '/como-funciona', 'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80'],
                    'de' => ['badge' => 'LIVE-DEMO', 'title' => 'Alle CMS-Blöcke in Aktion', 'content' => $heroContent['de'], 'cta_text' => 'Module entdecken', 'cta_url' => '/modulos', 'secondary_cta_text' => 'So funktioniert es', 'secondary_cta_url' => '/como-funciona', 'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80'],
                    'zh-CN' => ['badge' => '现场演示', 'title' => '所有 CMS 区块，一次看全', 'content' => $heroContent['zh-CN'], 'cta_text' => '查看模块', 'cta_url' => '/modulos', 'secondary_cta_text' => '工作原理', 'secondary_cta_url' => '/como-funciona', 'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80'],
                    'ar' => ['badge' => 'عرض توضيحي مباشر', 'title' => 'جميع وحدات نظام إدارة المحتوى في صفحة واحدة', 'content' => $heroContent['ar'], 'cta_text' => 'استعرض الوحدات', 'cta_url' => '/modulos', 'secondary_cta_text' => 'كيف تعمل المنصة', 'secondary_cta_url' => '/como-funciona', 'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80'],
                ],
            ],
            [
                'type' => 'kpi',
                'styling' => ['container_style' => 'cyber-glass', 'animation' => 'zoomIn', 'animation_delay' => '0.1s', 'bg_type' => 'image', 'bg_image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1600&q=80', 'bg_image_overlay' => 'rgba(0,0,0,0.62)', 'bg_behavior' => 'parallax'],
                'translations' => [
                    'es' => ['title' => 'La plataforma en números', 'items' => $kpiItems['es']],
                    'en' => ['title' => 'The platform in numbers', 'items' => $kpiItems['en']],
                    'fr' => ['title' => 'La plateforme en chiffres', 'items' => $kpiItems['fr']],
                    'it' => ['title' => 'La piattaforma in numeri', 'items' => $kpiItems['it']],
                    'pt' => ['title' => 'A plataforma em números', 'items' => $kpiItems['pt']],
                    'de' => ['title' => 'Die Plattform in Zahlen', 'items' => $kpiItems['de']],
                    'zh-CN' => ['title' => '平台数字一览', 'items' => $kpiItems['zh-CN']],
                    'ar' => ['title' => 'المنصة بالأرقام', 'items' => $kpiItems['ar']],
                ],
            ],
            [
                'type' => 'features',
                'styling' => ['container_style' => 'cyber-card', 'grid_columns' => '3', 'animation' => 'fadeInUp', 'animation_delay' => '0.15s'],
                'translations' => [
                    'es' => ['title' => 'Todo lo que puedes construir', 'items' => $featureItems['es']],
                    'en' => ['title' => 'Everything you can build', 'items' => $featureItems['en']],
                    'fr' => ['title' => 'Tout ce que vous pouvez construire', 'items' => $featureItems['fr']],
                    'it' => ['title' => 'Tutto ciò che puoi costruire', 'items' => $featureItems['it']],
                    'pt' => ['title' => 'Tudo o que podes construir', 'items' => $featureItems['pt']],
                    'de' => ['title' => 'Alles, was du bauen kannst', 'items' => $featureItems['de']],
                    'zh-CN' => ['title' => '你能构建的一切', 'items' => $featureItems['zh-CN']],
                    'ar' => ['title' => 'كل ما يمكنك بناءه', 'items' => $featureItems['ar']],
                ],
            ],
            [
                'type' => 'text',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.2s'],
                'translations' => [
                    'es' => [
                        'columns_layout' => '3',
                        'columns' => [
                            ['blocks' => [['type' => 'text', 'title' => 'Entidad', 'content' => 'La página, el artículo o el producto. Existe una sola vez, sin duplicados.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Traducción', 'content' => 'El contenido de esa entidad en cada idioma: título, slug, secciones y SEO.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Mercado', 'content' => 'Dónde se publica esa entidad. Añadir un país es añadir un mercado.']]],
                        ],
                    ],
                    'en' => [
                        'columns_layout' => '3',
                        'columns' => [
                            ['blocks' => [['type' => 'text', 'title' => 'Entity', 'content' => 'The page, the article or the product. It exists once, with no duplicates.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Translation', 'content' => 'That entity’s content in each language: title, slug, sections and SEO.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Market', 'content' => 'Where that entity is published. Adding a country means adding a market.']]],
                        ],
                    ],
                    'fr' => [
                        'columns_layout' => '3',
                        'columns' => [
                            ['blocks' => [['type' => 'text', 'title' => 'Entité', 'content' => 'La page, l’article ou le produit. Il existe une seule fois, sans doublons.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Traduction', 'content' => 'Le contenu de cette entité dans chaque langue : titre, slug, sections et SEO.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Marché', 'content' => 'Où cette entité est publiée. Ajouter un pays, c’est ajouter un marché.']]],
                        ],
                    ],
                    'it' => [
                        'columns_layout' => '3',
                        'columns' => [
                            ['blocks' => [['type' => 'text', 'title' => 'Entità', 'content' => 'La pagina, l’articolo o il prodotto. Esiste una sola volta, senza duplicati.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Traduzione', 'content' => 'Il contenuto di quell’entità in ogni lingua: titolo, slug, sezioni e SEO.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Mercato', 'content' => 'Dove viene pubblicata quell’entità. Aggiungere un paese significa aggiungere un mercato.']]],
                        ],
                    ],
                    'pt' => [
                        'columns_layout' => '3',
                        'columns' => [
                            ['blocks' => [['type' => 'text', 'title' => 'Entidade', 'content' => 'A página, o artigo ou o produto. Existe uma única vez, sem duplicados.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Tradução', 'content' => 'O conteúdo dessa entidade em cada idioma: título, slug, secções e SEO.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Mercado', 'content' => 'Onde essa entidade é publicada. Adicionar um país é adicionar um mercado.']]],
                        ],
                    ],
                    'de' => [
                        'columns_layout' => '3',
                        'columns' => [
                            ['blocks' => [['type' => 'text', 'title' => 'Entität', 'content' => 'Die Seite, der Artikel oder das Produkt. Es existiert nur einmal, ohne Duplikate.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Übersetzung', 'content' => 'Der Inhalt dieser Entität in jeder Sprache: Titel, Slug, Sektionen und SEO.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'Markt', 'content' => 'Wo diese Entität veröffentlicht wird. Ein neues Land ist ein neuer Markt.']]],
                        ],
                    ],
                    'zh-CN' => [
                        'columns_layout' => '3',
                        'columns' => [
                            ['blocks' => [['type' => 'text', 'title' => '实体', 'content' => '页面、文章或产品。只存在一次，绝不重复。']]],
                            ['blocks' => [['type' => 'text', 'title' => '翻译', 'content' => '该实体在各语言下的内容：标题、别名、版块与 SEO。']]],
                            ['blocks' => [['type' => 'text', 'title' => '市场', 'content' => '实体在哪里发布。增加一个国家，就是增加一个市场。']]],
                        ],
                    ],
                    'ar' => [
                        'columns_layout' => '3',
                        'columns' => [
                            ['blocks' => [['type' => 'text', 'title' => 'الكيان', 'content' => 'الصفحة أو المقال أو المنتج. يوجد مرة واحدة فقط، دون تكرار.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'الترجمة', 'content' => 'محتوى ذلك الكيان في كل لغة: العنوان والرابط والأقسام وSEO.']]],
                            ['blocks' => [['type' => 'text', 'title' => 'السوق', 'content' => 'مكان نشر ذلك الكيان. إضافة بلد جديد تعني إضافة سوق جديد.']]],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'pricing',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.25s'],
                'translations' => [
                    'es' => ['title' => 'Planes para cada escala', 'items' => $pricingItems['es']],
                    'en' => ['title' => 'Plans for every scale', 'items' => $pricingItems['en']],
                    'fr' => ['title' => 'Des offres pour chaque échelle', 'items' => $pricingItems['fr']],
                    'it' => ['title' => 'Piani per ogni scala', 'items' => $pricingItems['it']],
                    'pt' => ['title' => 'Planos para cada escala', 'items' => $pricingItems['pt']],
                    'de' => ['title' => 'Tarife für jede Größe', 'items' => $pricingItems['de']],
                    'zh-CN' => ['title' => '适合每个阶段的方案', 'items' => $pricingItems['zh-CN']],
                    'ar' => ['title' => 'خطط لكل مرحلة', 'items' => $pricingItems['ar']],
                ],
            ],
            [
                'type' => 'faq',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.3s'],
                'translations' => [
                    'es' => ['title' => 'Preguntas frecuentes', 'items' => [
                        ['question' => '¿Necesito duplicar el contenido por mercado?', 'answer' => 'No. El contenido existe una sola vez y se asigna a los mercados donde debe aparecer.'],
                        ['question' => '¿Cómo se gestiona el SEO por idioma?', 'answer' => 'Título, slug, meta descripción y Schema.org se editan por idioma; el mercado solo decide la visibilidad.'],
                        ['question' => '¿Puedo conectar asistentes de IA?', 'answer' => 'Sí. El servidor MCP expone el CMS con permisos acotados y auditoría de cada acción.'],
                        ['question' => '¿Qué ocurre si un mercado no tiene un idioma?', 'answer' => 'Cae al idioma por defecto del mercado, con un fallback predecible y sin contenido roto.'],
                    ]],
                    'en' => ['title' => 'Frequently asked questions', 'items' => [
                        ['question' => 'Do I need to duplicate content per market?', 'answer' => 'No. Content exists once and is assigned to the markets where it should appear.'],
                        ['question' => 'How is per-language SEO handled?', 'answer' => 'Title, slug, meta description and Schema.org are edited per language; the market only decides visibility.'],
                        ['question' => 'Can I connect AI assistants?', 'answer' => 'Yes. The MCP server exposes the CMS with scoped permissions and an audit trail for every action.'],
                        ['question' => 'What happens when a market lacks a language?', 'answer' => 'It falls back to the market default language, with a predictable fallback and no broken content.'],
                    ]],
                    'fr' => ['title' => 'Questions fréquentes', 'items' => [
                        ['question' => 'Faut-il dupliquer le contenu par marché ?', 'answer' => 'Non. Le contenu existe une seule fois et est affecté aux marchés où il doit apparaître.'],
                        ['question' => 'Comment le SEO par langue est-il géré ?', 'answer' => 'Titre, slug, méta description et Schema.org sont édités par langue ; le marché décide uniquement de la visibilité.'],
                        ['question' => 'Puis-je connecter des assistants IA ?', 'answer' => 'Oui. Le serveur MCP expose le CMS avec des permissions limitées et un audit de chaque action.'],
                        ['question' => 'Que se passe-t-il si un marché n’a pas de langue ?', 'answer' => 'Il bascule sur la langue par défaut du marché, avec un repli prévisible et sans contenu cassé.'],
                    ]],
                    'it' => ['title' => 'Domande frequenti', 'items' => [
                        ['question' => 'Devo duplicare i contenuti per mercato?', 'answer' => 'No. Il contenuto esiste una sola volta e viene assegnato ai mercati in cui deve apparire.'],
                        ['question' => 'Come si gestisce la SEO per lingua?', 'answer' => 'Titolo, slug, meta description e Schema.org si modificano per lingua; il mercato decide solo la visibilità.'],
                        ['question' => 'Posso collegare assistenti IA?', 'answer' => 'Sì. Il server MCP espone il CMS con permessi limitati e audit di ogni azione.'],
                        ['question' => 'Cosa succede se un mercato non ha una lingua?', 'answer' => 'Passa alla lingua predefinita del mercato, con un fallback prevedibile e senza contenuti rotti.'],
                    ]],
                    'pt' => ['title' => 'Perguntas frequentes', 'items' => [
                        ['question' => 'Preciso de duplicar conteúdo por mercado?', 'answer' => 'Não. O conteúdo existe uma única vez e é atribuído aos mercados onde deve aparecer.'],
                        ['question' => 'Como se gere o SEO por idioma?', 'answer' => 'Título, slug, meta descrição e Schema.org editam-se por idioma; o mercado apenas decide a visibilidade.'],
                        ['question' => 'Posso ligar assistentes de IA?', 'answer' => 'Sim. O servidor MCP expõe o CMS com permissões limitadas e auditoria de cada ação.'],
                        ['question' => 'O que acontece se um mercado não tiver um idioma?', 'answer' => 'Cai para o idioma predefinido do mercado, com fallback previsível e sem conteúdo partido.'],
                    ]],
                    'de' => ['title' => 'Häufige Fragen', 'items' => [
                        ['question' => 'Muss ich Inhalte pro Markt duplizieren?', 'answer' => 'Nein. Inhalte existieren einmal und werden den Märkten zugewiesen, in denen sie erscheinen sollen.'],
                        ['question' => 'Wie wird SEO pro Sprache verwaltet?', 'answer' => 'Titel, Slug, Meta-Description und Schema.org werden pro Sprache gepflegt; der Markt entscheidet nur über die Sichtbarkeit.'],
                        ['question' => 'Kann ich KI-Assistenten anbinden?', 'answer' => 'Ja. Der MCP-Server stellt das CMS mit eingeschränkten Rechten und Audit-Trail für jede Aktion bereit.'],
                        ['question' => 'Was passiert, wenn ein Markt keine Sprache hat?', 'answer' => 'Er fällt auf die Standardsprache des Marktes zurück — mit vorhersehbarem Fallback und ohne kaputte Inhalte.'],
                    ]],
                    'zh-CN' => ['title' => '常见问题', 'items' => [
                        ['question' => '我需要按市场复制内容吗？', 'answer' => '不需要。内容只存在一次，并分配到需要出现的市场。'],
                        ['question' => '如何按语言管理 SEO？', 'answer' => '标题、别名、元描述与 Schema.org 按语言编辑；市场只决定可见性。'],
                        ['question' => '可以连接 AI 助手吗？', 'answer' => '可以。MCP 服务器以受限权限开放 CMS，并对每项操作进行审计。'],
                        ['question' => '某个市场没有对应语言会怎样？', 'answer' => '自动回退到该市场的默认语言，回退可预期，内容不会缺失。'],
                    ]],
                    'ar' => ['title' => 'الأسئلة الشائعة', 'items' => [
                        ['question' => 'هل أحتاج إلى تكرار المحتوى لكل سوق؟', 'answer' => 'لا. المحتوى يوجد مرة واحدة ويُخصص للأسواق التي يجب أن يظهر فيها.'],
                        ['question' => 'كيف تتم إدارة SEO لكل لغة؟', 'answer' => 'العنوان والرابط والوصف وSchema.org تُحرر لكل لغة؛ والسوق يقرر الرؤية فقط.'],
                        ['question' => 'هل يمكنني ربط مساعدي الذكاء الاصطناعي؟', 'answer' => 'نعم. يعرض خادم MCP نظام إدارة المحتوى بصلاحيات محدودة مع تدقيق لكل إجراء.'],
                        ['question' => 'ماذا يحدث إذا لم يكن للسوق لغة؟', 'answer' => 'يتم الرجوع إلى اللغة الافتراضية للسوق، مع سلوك رجوع متوقع ودون محتوى مكسور.'],
                    ]],
                ],
            ],
            [
                'type' => 'testimonials',
                'styling' => ['container_style' => 'transparent', 'animation' => 'zoomIn', 'animation_delay' => '0.35s'],
                'translations' => [
                    'es' => ['title' => 'Lo que dicen los equipos', 'items' => [
                        ['author' => 'Elena Martínez', 'role' => 'CTO · TechNexus', 'quote' => 'Pasamos de semanas a minutos en los despliegues internacionales.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'],
                        ['author' => 'Marc Soler', 'role' => 'Lead Architect · DataCore', 'quote' => 'El soporte GEO multiplicó nuestras citas en motores de IA.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200'],
                        ['author' => 'Amina Zahra', 'role' => 'Head of Growth · Gulf Retail', 'quote' => 'El árabe RTL funcionó desde el primer día, sin retoques.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200'],
                    ]],
                    'en' => ['title' => 'What teams are saying', 'items' => [
                        ['author' => 'Elena Martínez', 'role' => 'CTO · TechNexus', 'quote' => 'International deploys went from weeks to minutes.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'],
                        ['author' => 'Marc Soler', 'role' => 'Lead Architect · DataCore', 'quote' => 'GEO support multiplied our citations in AI engines.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200'],
                        ['author' => 'Amina Zahra', 'role' => 'Head of Growth · Gulf Retail', 'quote' => 'Arabic RTL worked from day one, with zero tweaks.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200'],
                    ]],
                    'fr' => ['title' => 'Ce que disent les équipes', 'items' => [
                        ['author' => 'Elena Martínez', 'role' => 'CTO · TechNexus', 'quote' => 'Les déploiements internationaux passent de semaines à minutes.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'],
                        ['author' => 'Marc Soler', 'role' => 'Lead Architect · DataCore', 'quote' => 'Le support GEO a multiplié nos citations dans les moteurs IA.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200'],
                        ['author' => 'Amina Zahra', 'role' => 'Head of Growth · Gulf Retail', 'quote' => 'L’arabe RTL a fonctionné dès le premier jour, sans retouche.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200'],
                    ]],
                    'it' => ['title' => 'Cosa dicono i team', 'items' => [
                        ['author' => 'Elena Martínez', 'role' => 'CTO · TechNexus', 'quote' => 'I deploy internazionali passano da settimane a minuti.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'],
                        ['author' => 'Marc Soler', 'role' => 'Lead Architect · DataCore', 'quote' => 'Il supporto GEO ha moltiplicato le nostre citazioni nei motori IA.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200'],
                        ['author' => 'Amina Zahra', 'role' => 'Head of Growth · Gulf Retail', 'quote' => 'L’arabo RTL ha funzionato dal primo giorno, senza ritocchi.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200'],
                    ]],
                    'pt' => ['title' => 'O que dizem as equipas', 'items' => [
                        ['author' => 'Elena Martínez', 'role' => 'CTO · TechNexus', 'quote' => 'Os deploys internacionais passaram de semanas para minutos.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'],
                        ['author' => 'Marc Soler', 'role' => 'Lead Architect · DataCore', 'quote' => 'O suporte GEO multiplicou as nossas citações em motores de IA.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200'],
                        ['author' => 'Amina Zahra', 'role' => 'Head of Growth · Gulf Retail', 'quote' => 'O árabe RTL funcionou desde o primeiro dia, sem retoques.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200'],
                    ]],
                    'de' => ['title' => 'Was die Teams sagen', 'items' => [
                        ['author' => 'Elena Martínez', 'role' => 'CTO · TechNexus', 'quote' => 'Internationale Deploys dauern jetzt Minuten statt Wochen.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'],
                        ['author' => 'Marc Soler', 'role' => 'Lead Architect · DataCore', 'quote' => 'Die GEO-Unterstützung hat unsere Zitate in KI-Engines vervielfacht.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200'],
                        ['author' => 'Amina Zahra', 'role' => 'Head of Growth · Gulf Retail', 'quote' => 'Arabisch RTL lief ab Tag eins — ganz ohne Nacharbeit.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200'],
                    ]],
                    'zh-CN' => ['title' => '团队怎么说', 'items' => [
                        ['author' => 'Elena Martínez', 'role' => 'CTO · TechNexus', 'quote' => '国际部署从数周缩短到几分钟。', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'],
                        ['author' => 'Marc Soler', 'role' => 'Lead Architect · DataCore', 'quote' => 'GEO 支持让 AI 引擎引用量成倍增长。', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200'],
                        ['author' => 'Amina Zahra', 'role' => 'Head of Growth · Gulf Retail', 'quote' => '阿拉伯语 RTL 开箱即用，零调整。', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200'],
                    ]],
                    'ar' => ['title' => 'ماذا تقول الفرق', 'items' => [
                        ['author' => 'Elena Martínez', 'role' => 'CTO · TechNexus', 'quote' => 'تحولت عمليات النشر الدولية من أسابيع إلى دقائق.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'],
                        ['author' => 'Marc Soler', 'role' => 'Lead Architect · DataCore', 'quote' => 'دعم GEO ضاعف اقتباساتنا في محركات الذكاء الاصطناعي.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200'],
                        ['author' => 'Amina Zahra', 'role' => 'Head of Growth · Gulf Retail', 'quote' => 'اللغة العربية RTL عملت من اليوم الأول دون أي تعديل.', 'rating' => 5, 'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200'],
                    ]],
                ],
            ],
            [
                'type' => 'carousel',
                'styling' => ['container_style' => 'transparent', 'animation' => 'fadeInUp', 'animation_delay' => '0.4s'],
                'translations' => [
                    'es' => ['title' => 'Un vistazo al producto', 'height' => 460, 'autoplay' => true, 'interval' => 6000, 'slides' => [
                        ['image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1400&q=80', 'title' => 'Editor de secciones', 'subtitle' => 'Columnas, fondos y animaciones desde el panel.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80', 'title' => 'Matriz de mercados', 'subtitle' => 'Un contenido, ocho mercados e idiomas.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80', 'title' => 'Conexión MCP', 'subtitle' => 'Agentes de IA con permisos acotados.'],
                    ]],
                    'en' => ['title' => 'A look at the product', 'height' => 460, 'autoplay' => true, 'interval' => 6000, 'slides' => [
                        ['image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1400&q=80', 'title' => 'Section editor', 'subtitle' => 'Columns, backgrounds and animations from the panel.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80', 'title' => 'Market matrix', 'subtitle' => 'One content item, eight markets and languages.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80', 'title' => 'MCP connection', 'subtitle' => 'AI agents with scoped permissions.'],
                    ]],
                    'fr' => ['title' => 'Un aperçu du produit', 'height' => 460, 'autoplay' => true, 'interval' => 6000, 'slides' => [
                        ['image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1400&q=80', 'title' => 'Éditeur de sections', 'subtitle' => 'Colonnes, arrière-plans et animations depuis le panneau.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80', 'title' => 'Matrice de marchés', 'subtitle' => 'Un contenu, huit marchés et langues.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80', 'title' => 'Connexion MCP', 'subtitle' => 'Agents IA avec permissions limitées.'],
                    ]],
                    'it' => ['title' => 'Uno sguardo al prodotto', 'height' => 460, 'autoplay' => true, 'interval' => 6000, 'slides' => [
                        ['image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1400&q=80', 'title' => 'Editor di sezioni', 'subtitle' => 'Colonne, sfondi e animazioni dal pannello.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80', 'title' => 'Matrice dei mercati', 'subtitle' => 'Un contenuto, otto mercati e lingue.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80', 'title' => 'Connessione MCP', 'subtitle' => 'Agenti IA con permessi limitati.'],
                    ]],
                    'pt' => ['title' => 'Um olhar ao produto', 'height' => 460, 'autoplay' => true, 'interval' => 6000, 'slides' => [
                        ['image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1400&q=80', 'title' => 'Editor de secções', 'subtitle' => 'Colunas, fundos e animações a partir do painel.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80', 'title' => 'Matriz de mercados', 'subtitle' => 'Um conteúdo, oito mercados e idiomas.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80', 'title' => 'Ligação MCP', 'subtitle' => 'Agentes de IA com permissões limitadas.'],
                    ]],
                    'de' => ['title' => 'Ein Blick auf das Produkt', 'height' => 460, 'autoplay' => true, 'interval' => 6000, 'slides' => [
                        ['image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1400&q=80', 'title' => 'Sektions-Editor', 'subtitle' => 'Spalten, Hintergründe und Animationen aus dem Panel.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80', 'title' => 'Markt-Matrix', 'subtitle' => 'Ein Inhalt, acht Märkte und Sprachen.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80', 'title' => 'MCP-Verbindung', 'subtitle' => 'KI-Agenten mit eingeschränkten Rechten.'],
                    ]],
                    'zh-CN' => ['title' => '产品一览', 'height' => 460, 'autoplay' => true, 'interval' => 6000, 'slides' => [
                        ['image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1400&q=80', 'title' => '版块编辑器', 'subtitle' => '从后台配置栏目、背景与动画。'],
                        ['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80', 'title' => '市场矩阵', 'subtitle' => '一份内容，八个市场与语言。'],
                        ['image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80', 'title' => 'MCP 连接', 'subtitle' => '权限受限的 AI 代理。'],
                    ]],
                    'ar' => ['title' => 'نظرة على المنتج', 'height' => 460, 'autoplay' => true, 'interval' => 6000, 'slides' => [
                        ['image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1400&q=80', 'title' => 'محرر الأقسام', 'subtitle' => 'أعمدة وخلفيات وحركات من لوحة التحكم.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1400&q=80', 'title' => 'مصفوفة الأسواق', 'subtitle' => 'محتوى واحد، ثمانية أسواق ولغات.'],
                        ['image_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1400&q=80', 'title' => 'اتصال MCP', 'subtitle' => 'وكلاء ذكاء اصطناعي بصلاحيات محدودة.'],
                    ]],
                ],
            ],
            [
                'type' => 'before_after',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'zoomIn', 'animation_delay' => '0.45s'],
                'translations' => [
                    'es' => ['title' => 'Del monolito a una sola base de datos', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'ANTES: N despliegues', 'after_label' => 'DESPUÉS: dbCMS'],
                    'en' => ['title' => 'From monolith to a single database', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'BEFORE: N deploys', 'after_label' => 'AFTER: dbCMS'],
                    'fr' => ['title' => 'Du monolithe à une seule base de données', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'AVANT : N déploiements', 'after_label' => 'APRÈS : dbCMS'],
                    'it' => ['title' => 'Dal monolite a un unico database', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'PRIMA: N deploy', 'after_label' => 'DOPO: dbCMS'],
                    'pt' => ['title' => 'Do monólito a uma única base de dados', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'ANTES: N deploys', 'after_label' => 'DEPOIS: dbCMS'],
                    'de' => ['title' => 'Vom Monolithen zu einer einzigen Datenbank', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'VORHER: N Deploys', 'after_label' => 'NACHHER: dbCMS'],
                    'zh-CN' => ['title' => '从单体架构到一个数据库', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => '之前：多次部署', 'after_label' => '之后：dbCMS'],
                    'ar' => ['title' => 'من البنية الأحادية إلى قاعدة بيانات واحدة', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'قبل: عمليات نشر متعددة', 'after_label' => 'بعد: dbCMS'],
                ],
            ],
        ];
    }

    /**
     * Showcase sections (part 2/2): code, roi, search, widgets, gallery, charts
     * and the closing CTA.
     *
     * @return list<array{type: string, styling?: array<string, mixed>, translations: array<string, array<string, mixed>>}>
     */
    protected function showcaseSectionsB(): array
    {
        $codeSnippet = <<<'CODE'
{
    "type": "features",
    "styling": { "columns_layout": "3", "animation": "fadeInUp" },
    "content": {
        "title": "Lo que hace la plataforma",
        "items": [
            { "icon": "🌍", "title": "Multi-mercado", "description": "Un contenido, varios mercados." }
        ]
    }
}
CODE;

        return [
            [
                'type' => 'code_embed',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.5s'],
                'translations' => [
                    'es' => ['title' => 'Configuración declarativa', 'content' => $codeSnippet],
                    'en' => ['title' => 'Declarative configuration', 'content' => $codeSnippet],
                    'fr' => ['title' => 'Configuration déclarative', 'content' => $codeSnippet],
                    'it' => ['title' => 'Configurazione dichiarativa', 'content' => $codeSnippet],
                    'pt' => ['title' => 'Configuração declarativa', 'content' => $codeSnippet],
                    'de' => ['title' => 'Deklarative Konfiguration', 'content' => $codeSnippet],
                    'zh-CN' => ['title' => '声明式配置', 'content' => $codeSnippet],
                    'ar' => ['title' => 'تكوين تعريفي', 'content' => $codeSnippet],
                ],
            ],
            [
                'type' => 'roi',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.55s'],
                'translations' => [
                    'es' => ['title' => 'El retorno de la inversión', 'description' => 'Comprueba el impacto directo de consolidar mercados e idiomas en una sola plataforma.'],
                    'en' => ['title' => 'The return on investment', 'description' => 'See the direct impact of consolidating markets and languages into one platform.'],
                    'fr' => ['title' => 'Le retour sur investissement', 'description' => 'Mesurez l’impact direct de la consolidation des marchés et des langues sur une seule plateforme.'],
                    'it' => ['title' => 'Il ritorno sull’investimento', 'description' => 'Misura l’impatto diretto del consolidare mercati e lingue in un’unica piattaforma.'],
                    'pt' => ['title' => 'O retorno do investimento', 'description' => 'Vê o impacto direto de consolidar mercados e idiomas numa única plataforma.'],
                    'de' => ['title' => 'Der Return on Investment', 'description' => 'Sieh dir den direkten Effekt an, wenn Märkte und Sprachen auf einer Plattform gebündelt werden.'],
                    'zh-CN' => ['title' => '投资回报', 'description' => '直观感受将市场与语言整合到一个平台的直接收益。'],
                    'ar' => ['title' => 'العائد على الاستثمار', 'description' => 'اطلع على الأثر المباشر لتوحيد الأسواق واللغات في منصة واحدة.'],
                ],
            ],
            [
                'type' => 'search_box',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.6s'],
                'translations' => [
                    'es' => ['title' => 'Prueba la búsqueda', 'size' => 'lg', 'placeholder' => 'Busca «mercado» o «arquitectura»…'],
                    'en' => ['title' => 'Try the search', 'size' => 'lg', 'placeholder' => 'Search “market” or “architecture”…'],
                    'fr' => ['title' => 'Essayez la recherche', 'size' => 'lg', 'placeholder' => 'Cherchez « marché » ou « architecture »…'],
                    'it' => ['title' => 'Prova la ricerca', 'size' => 'lg', 'placeholder' => 'Cerca «mercato» o «architettura»…'],
                    'pt' => ['title' => 'Experimenta a pesquisa', 'size' => 'lg', 'placeholder' => 'Pesquisa «mercado» ou «arquitetura»…'],
                    'de' => ['title' => 'Teste die Suche', 'size' => 'lg', 'placeholder' => 'Suche nach „Markt“ oder „Architektur“…'],
                    'zh-CN' => ['title' => '试试搜索', 'size' => 'lg', 'placeholder' => '搜索“市场”或“架构”…'],
                    'ar' => ['title' => 'جرّب البحث', 'size' => 'lg', 'placeholder' => 'ابحث عن «سوق» أو «هندسة»…'],
                ],
            ],
            [
                'type' => 'widget_posts',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.65s'],
                'translations' => [
                    'es' => ['title' => 'Últimos artículos del blog', 'limit' => 3, 'grid_columns' => '3'],
                    'en' => ['title' => 'Latest blog posts', 'limit' => 3, 'grid_columns' => '3'],
                    'fr' => ['title' => 'Derniers articles du blog', 'limit' => 3, 'grid_columns' => '3'],
                    'it' => ['title' => 'Ultimi articoli del blog', 'limit' => 3, 'grid_columns' => '3'],
                    'pt' => ['title' => 'Últimos artigos do blogue', 'limit' => 3, 'grid_columns' => '3'],
                    'de' => ['title' => 'Neueste Blogartikel', 'limit' => 3, 'grid_columns' => '3'],
                    'zh-CN' => ['title' => '最新博客文章', 'limit' => 3, 'grid_columns' => '3'],
                    'ar' => ['title' => 'أحدث مقالات المدونة', 'limit' => 3, 'grid_columns' => '3'],
                ],
            ],
            [
                'type' => 'widget_products',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.7s'],
                'translations' => [
                    'es' => ['title' => 'Productos destacados', 'limit' => 4, 'grid_columns' => '4'],
                    'en' => ['title' => 'Featured products', 'limit' => 4, 'grid_columns' => '4'],
                    'fr' => ['title' => 'Produits à la une', 'limit' => 4, 'grid_columns' => '4'],
                    'it' => ['title' => 'Prodotti in evidenza', 'limit' => 4, 'grid_columns' => '4'],
                    'pt' => ['title' => 'Produtos em destaque', 'limit' => 4, 'grid_columns' => '4'],
                    'de' => ['title' => 'Ausgewählte Produkte', 'limit' => 4, 'grid_columns' => '4'],
                    'zh-CN' => ['title' => '精选产品', 'limit' => 4, 'grid_columns' => '4'],
                    'ar' => ['title' => 'منتجات مميزة', 'limit' => 4, 'grid_columns' => '4'],
                ],
            ],
            [
                'type' => 'gallery',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp', 'animation_delay' => '0.75s'],
                'translations' => [
                    'es' => ['title' => 'Recorrido por la plataforma', 'album_slug' => 'recorrido-plataforma', 'limit' => 6, 'grid_columns' => '3'],
                    'en' => ['title' => 'A tour of the platform', 'album_slug' => 'recorrido-plataforma', 'limit' => 6, 'grid_columns' => '3'],
                    'fr' => ['title' => 'Visite de la plateforme', 'album_slug' => 'recorrido-plataforma', 'limit' => 6, 'grid_columns' => '3'],
                    'it' => ['title' => 'Tour della piattaforma', 'album_slug' => 'recorrido-plataforma', 'limit' => 6, 'grid_columns' => '3'],
                    'pt' => ['title' => 'Visita à plataforma', 'album_slug' => 'recorrido-plataforma', 'limit' => 6, 'grid_columns' => '3'],
                    'de' => ['title' => 'Rundgang durch die Plattform', 'album_slug' => 'recorrido-plataforma', 'limit' => 6, 'grid_columns' => '3'],
                    'zh-CN' => ['title' => '平台导览', 'album_slug' => 'recorrido-plataforma', 'limit' => 6, 'grid_columns' => '3'],
                    'ar' => ['title' => 'جولة في المنصة', 'album_slug' => 'recorrido-plataforma', 'limit' => 6, 'grid_columns' => '3'],
                ],
            ],
            [
                'type' => 'charts',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'zoomIn', 'animation_delay' => '0.8s'],
                'translations' => [
                    'es' => ['title' => 'Rendimiento por módulo', 'chart_type' => 'bar', 'items' => [
                        ['label' => 'Velocidad de carga', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                        ['label' => 'Indexación SEO y GEO', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                        ['label' => 'Conversión de leads', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
                    ]],
                    'en' => ['title' => 'Performance per module', 'chart_type' => 'bar', 'items' => [
                        ['label' => 'Load speed', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                        ['label' => 'SEO & GEO indexing', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                        ['label' => 'Lead conversion', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
                    ]],
                    'fr' => ['title' => 'Performance par module', 'chart_type' => 'bar', 'items' => [
                        ['label' => 'Vitesse de chargement', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                        ['label' => 'Indexation SEO & GEO', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                        ['label' => 'Conversion de leads', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
                    ]],
                    'it' => ['title' => 'Prestazioni per modulo', 'chart_type' => 'bar', 'items' => [
                        ['label' => 'Velocità di caricamento', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                        ['label' => 'Indicizzazione SEO e GEO', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                        ['label' => 'Conversione dei lead', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
                    ]],
                    'pt' => ['title' => 'Desempenho por módulo', 'chart_type' => 'bar', 'items' => [
                        ['label' => 'Velocidade de carregamento', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                        ['label' => 'Indexação SEO e GEO', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                        ['label' => 'Conversão de leads', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
                    ]],
                    'de' => ['title' => 'Leistung pro Modul', 'chart_type' => 'bar', 'items' => [
                        ['label' => 'Ladegeschwindigkeit', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                        ['label' => 'SEO- & GEO-Indexierung', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                        ['label' => 'Lead-Conversion', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
                    ]],
                    'zh-CN' => ['title' => '模块性能', 'chart_type' => 'bar', 'items' => [
                        ['label' => '加载速度', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                        ['label' => 'SEO 与 GEO 收录', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                        ['label' => '线索转化', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
                    ]],
                    'ar' => ['title' => 'الأداء حسب الوحدة', 'chart_type' => 'bar', 'items' => [
                        ['label' => 'سرعة التحميل', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                        ['label' => 'فهرسة SEO وGEO', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                        ['label' => 'تحويل العملاء المحتملين', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
                    ]],
                ],
            ],
            [
                'type' => 'cta',
                'styling' => ['container_style' => 'cyber-glass', 'animation' => 'fadeInUp', 'animation_delay' => '0.85s', 'divider_top' => ['type' => 'tilt', 'height' => 40]],
                'translations' => [
                    'es' => ['title' => '¿Listo para tu propio dbCMS?', 'content' => 'Activa los módulos que necesites, elige tus mercados e idiomas y publica.', 'cta_text' => 'Contactar', 'cta_url' => '/contacto'],
                    'en' => ['title' => 'Ready for your own dbCMS?', 'content' => 'Enable the modules you need, pick your markets and languages, and publish.', 'cta_text' => 'Get in touch', 'cta_url' => '/contacto'],
                    'fr' => ['title' => 'Prêt pour votre propre dbCMS ?', 'content' => 'Activez les modules dont vous avez besoin, choisissez vos marchés et langues, puis publiez.', 'cta_text' => 'Nous contacter', 'cta_url' => '/contacto'],
                    'it' => ['title' => 'Pronto per il tuo dbCMS?', 'content' => 'Attiva i moduli che ti servono, scegli mercati e lingue e pubblica.', 'cta_text' => 'Contattaci', 'cta_url' => '/contacto'],
                    'pt' => ['title' => 'Pronto para o teu dbCMS?', 'content' => 'Ativa os módulos de que precisas, escolhe mercados e idiomas e publica.', 'cta_text' => 'Contactar', 'cta_url' => '/contacto'],
                    'de' => ['title' => 'Bereit für dein eigenes dbCMS?', 'content' => 'Aktiviere die Module, die du brauchst, wähle Märkte und Sprachen und veröffentliche.', 'cta_text' => 'Kontakt aufnehmen', 'cta_url' => '/contacto'],
                    'zh-CN' => ['title' => '准备好构建你自己的 dbCMS 了吗？', 'content' => '启用所需模块，选择市场与语言，然后发布。', 'cta_text' => '联系我们', 'cta_url' => '/contacto'],
                    'ar' => ['title' => 'جاهز لنظام dbCMS الخاص بك؟', 'content' => 'فعّل الوحدات التي تحتاجها، واختر أسواقك ولغاتك، ثم انشر.', 'cta_text' => 'تواصل معنا', 'cta_url' => '/contacto'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{type: string, styling?: array<string, mixed>, translations: array<string, array<string, mixed>>}>
     */
    protected function homeEnrichmentSections(): array
    {
        return [
            [
                'type' => 'kpi',
                'styling' => ['container_style' => 'transparent', 'animation' => 'zoomIn', 'animation_delay' => '0.2s'],
                'translations' => [
                    'es' => ['title' => 'La plataforma en números', 'items' => [['8', 'Idiomas activos'], ['8', 'Mercados en vivo'], ['18+', 'Bloques listos'], ['100%', 'Suite en verde']]],
                    'en' => ['title' => 'The platform in numbers', 'items' => [['8', 'Active languages'], ['8', 'Live markets'], ['18+', 'Ready-made blocks'], ['100%', 'Test suite green']]],
                    'fr' => ['title' => 'La plateforme en chiffres', 'items' => [['8', 'Langues actives'], ['8', 'Marchés actifs'], ['18+', 'Blocs prêts'], ['100%', 'Suite de tests verte']]],
                    'it' => ['title' => 'La piattaforma in numeri', 'items' => [['8', 'Lingue attive'], ['8', 'Mercati attivi'], ['18+', 'Blocchi pronti'], ['100%', 'Suite di test verde']]],
                    'pt' => ['title' => 'A plataforma em números', 'items' => [['8', 'Idiomas ativos'], ['8', 'Mercados ativos'], ['18+', 'Blocos prontos'], ['100%', 'Suíte de testes verde']]],
                    'de' => ['title' => 'Die Plattform in Zahlen', 'items' => [['8', 'Aktive Sprachen'], ['8', 'Aktive Märkte'], ['18+', 'Einsatzbereite Blöcke'], ['100%', 'Test-Suite grün']]],
                    'zh-CN' => ['title' => '平台数字一览', 'items' => [['8', '活跃语言'], ['8', '活跃市场'], ['18+', '可用区块'], ['100%', '测试全部通过']]],
                    'ar' => ['title' => 'المنصة بالأرقام', 'items' => [['8', 'لغات نشطة'], ['8', 'أسواق نشطة'], ['18+', 'وحدات جاهزة'], ['100%', 'اختبارات خضراء']]],
                ],
            ],
            [
                'type' => 'widget_posts',
                'styling' => ['container_style' => 'transparent', 'animation' => 'fadeInUp', 'animation_delay' => '0.3s'],
                'translations' => [
                    'es' => ['title' => 'Últimos artículos', 'limit' => 3, 'grid_columns' => '3'],
                    'en' => ['title' => 'Latest posts', 'limit' => 3, 'grid_columns' => '3'],
                    'fr' => ['title' => 'Derniers articles', 'limit' => 3, 'grid_columns' => '3'],
                    'it' => ['title' => 'Ultimi articoli', 'limit' => 3, 'grid_columns' => '3'],
                    'pt' => ['title' => 'Últimos artigos', 'limit' => 3, 'grid_columns' => '3'],
                    'de' => ['title' => 'Neueste Artikel', 'limit' => 3, 'grid_columns' => '3'],
                    'zh-CN' => ['title' => '最新文章', 'limit' => 3, 'grid_columns' => '3'],
                    'ar' => ['title' => 'أحدث المقالات', 'limit' => 3, 'grid_columns' => '3'],
                ],
            ],
            [
                'type' => 'cta',
                'styling' => ['container_style' => 'cyber-glass', 'animation' => 'fadeInUp', 'animation_delay' => '0.4s'],
                'translations' => [
                    'es' => ['title' => 'Explora la demo completa', 'content' => 'Todos los bloques, todos los módulos y la matriz de mercados en una sola página.', 'cta_text' => 'Ver los bloques', 'cta_url' => '/bloques'],
                    'en' => ['title' => 'Explore the full demo', 'content' => 'Every block, every module and the whole market matrix on a single page.', 'cta_text' => 'See the blocks', 'cta_url' => '/bloques'],
                    'fr' => ['title' => 'Explorez la démo complète', 'content' => 'Tous les blocs, tous les modules et la matrice de marchés sur une seule page.', 'cta_text' => 'Voir les blocs', 'cta_url' => '/bloques'],
                    'it' => ['title' => 'Esplora la demo completa', 'content' => 'Tutti i blocchi, tutti i moduli e la matrice dei mercati in una sola pagina.', 'cta_text' => 'Vedi i blocchi', 'cta_url' => '/bloques'],
                    'pt' => ['title' => 'Explora a demonstração completa', 'content' => 'Todos os blocos, todos os módulos e a matriz de mercados numa só página.', 'cta_text' => 'Ver os blocos', 'cta_url' => '/bloques'],
                    'de' => ['title' => 'Entdecke die komplette Demo', 'content' => 'Alle Blöcke, alle Module und die gesamte Markt-Matrix auf einer Seite.', 'cta_text' => 'Blöcke ansehen', 'cta_url' => '/bloques'],
                    'zh-CN' => ['title' => '探索完整演示', 'content' => '所有区块、所有模块与整个市场矩阵，尽在一页。', 'cta_text' => '查看区块', 'cta_url' => '/bloques'],
                    'ar' => ['title' => 'استكشف العرض الكامل', 'content' => 'كل الوحدات وكل الإضافات ومصفوفة الأسواق كاملة في صفحة واحدة.', 'cta_text' => 'شاهد الوحدات', 'cta_url' => '/bloques'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{type: string, styling?: array<string, mixed>, translations: array<string, array<string, mixed>>}>
     */
    protected function howItWorksSections(): array
    {
        return [
            [
                'type' => 'text',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'fadeInUp'],
                'translations' => [
                    'es' => ['title' => 'Entidad, traducción y mercado', 'content' => "1. **Entidad**: la página, el artículo o el producto. Existe una sola vez.\n2. **Traducción**: el contenido de esa entidad en cada idioma.\n3. **Mercado**: dónde se publica esa entidad.\n\nAl entrar en `/eu/es/...` se resuelven mercado e idioma y se sirve la pieza correcta. Añadir un país nuevo es añadir un mercado, no duplicar contenido."],
                    'en' => ['title' => 'Entity, translation and market', 'content' => "1. **Entity**: the page, article or product. It exists once.\n2. **Translation**: that entity's content in each language.\n3. **Market**: where that entity is published.\n\nLanding on `/eu/en/...` resolves market and language and serves the right piece. Adding a new country means adding a market, not duplicating content."],
                    'fr' => ['title' => 'Entité, traduction et marché', 'content' => "1. **Entité** : la page, l’article ou le produit. Il existe une seule fois.\n2. **Traduction** : le contenu de cette entité dans chaque langue.\n3. **Marché** : où cette entité est publiée.\n\nEn arrivant sur `/eu/fr/...`, le marché et la langue sont résolus et la bonne pièce est servie. Ajouter un pays, c’est ajouter un marché, pas dupliquer du contenu."],
                    'it' => ['title' => 'Entità, traduzione e mercato', 'content' => "1. **Entità**: la pagina, l’articolo o il prodotto. Esiste una sola volta.\n2. **Traduzione**: il contenuto di quell’entità in ogni lingua.\n3. **Mercato**: dove viene pubblicata quell’entità.\n\nEntrando su `/eu/it/...` si risolvono mercato e lingua e si serve il pezzo giusto. Aggiungere un paese significa aggiungere un mercato, non duplicare contenuti."],
                    'pt' => ['title' => 'Entidade, tradução e mercado', 'content' => "1. **Entidade**: a página, o artigo ou o produto. Existe uma única vez.\n2. **Tradução**: o conteúdo dessa entidade em cada idioma.\n3. **Mercado**: onde essa entidade é publicada.\n\nAo entrar em `/eu/pt/...`, resolvem-se mercado e idioma e serve-se a peça certa. Adicionar um país é adicionar um mercado, não duplicar conteúdo."],
                    'de' => ['title' => 'Entität, Übersetzung und Markt', 'content' => "1. **Entität**: die Seite, der Artikel oder das Produkt. Es existiert nur einmal.\n2. **Übersetzung**: der Inhalt dieser Entität in jeder Sprache.\n3. **Markt**: wo diese Entität veröffentlicht wird.\n\nBeim Aufruf von `/eu/de/...` werden Markt und Sprache aufgelöst und das richtige Stück ausgeliefert. Ein neues Land ist ein neuer Markt, keine duplizierten Inhalte."],
                    'zh-CN' => ['title' => '实体、翻译与市场', 'content' => "1. **实体**：页面、文章或产品，只存在一次。\n2. **翻译**：该实体在各语言下的内容。\n3. **市场**：实体在哪里发布。\n\n访问 `/eu/zh-CN/...` 时解析市场与语言，输出正确内容。新增国家就是新增市场，而不是复制内容。"],
                    'ar' => ['title' => 'الكيان والترجمة والسوق', 'content' => "1. **الكيان**: الصفحة أو المقال أو المنتج، يوجد مرة واحدة.\n2. **الترجمة**: محتوى ذلك الكيان في كل لغة.\n3. **السوق**: مكان نشر ذلك الكيان.\n\nعند زيارة `/eu/ar/...` تُحل السوق واللغة ويُعرض المحتوى الصحيح. إضافة بلد جديد تعني إضافة سوق، لا تكرار المحتوى."],
                ],
            ],
            [
                'type' => 'features',
                'styling' => ['container_style' => 'cyber-card', 'grid_columns' => '4', 'animation' => 'fadeInUp', 'animation_delay' => '0.15s'],
                'translations' => [
                    'es' => ['title' => 'El flujo de publicación', 'items' => [
                        ['icon' => '1️⃣', 'title' => 'Crea', 'description' => 'La entidad nace una vez, con sus secciones.'],
                        ['icon' => '2️⃣', 'title' => 'Traduce', 'description' => 'Título, slug y SEO por idioma.'],
                        ['icon' => '3️⃣', 'title' => 'Asigna', 'description' => 'Marca los mercados donde aparece.'],
                        ['icon' => '4️⃣', 'title' => 'Publica', 'description' => 'Caché, CDN y sitemaps al instante.'],
                    ]],
                    'en' => ['title' => 'The publishing flow', 'items' => [
                        ['icon' => '1️⃣', 'title' => 'Create', 'description' => 'The entity is born once, with its sections.'],
                        ['icon' => '2️⃣', 'title' => 'Translate', 'description' => 'Title, slug and SEO per language.'],
                        ['icon' => '3️⃣', 'title' => 'Assign', 'description' => 'Pick the markets where it shows up.'],
                        ['icon' => '4️⃣', 'title' => 'Publish', 'description' => 'Cache, CDN and sitemaps instantly.'],
                    ]],
                    'fr' => ['title' => 'Le flux de publication', 'items' => [
                        ['icon' => '1️⃣', 'title' => 'Créez', 'description' => 'L’entité naît une fois, avec ses sections.'],
                        ['icon' => '2️⃣', 'title' => 'Traduisez', 'description' => 'Titre, slug et SEO par langue.'],
                        ['icon' => '3️⃣', 'title' => 'Affectez', 'description' => 'Choisissez les marchés où elle apparaît.'],
                        ['icon' => '4️⃣', 'title' => 'Publiez', 'description' => 'Cache, CDN et sitemaps instantanément.'],
                    ]],
                    'it' => ['title' => 'Il flusso di pubblicazione', 'items' => [
                        ['icon' => '1️⃣', 'title' => 'Crea', 'description' => 'L’entità nasce una volta, con le sue sezioni.'],
                        ['icon' => '2️⃣', 'title' => 'Traduci', 'description' => 'Titolo, slug e SEO per lingua.'],
                        ['icon' => '3️⃣', 'title' => 'Assegna', 'description' => 'Scegli i mercati in cui appare.'],
                        ['icon' => '4️⃣', 'title' => 'Pubblica', 'description' => 'Cache, CDN e sitemap all’istante.'],
                    ]],
                    'pt' => ['title' => 'O fluxo de publicação', 'items' => [
                        ['icon' => '1️⃣', 'title' => 'Cria', 'description' => 'A entidade nasce uma vez, com as suas secções.'],
                        ['icon' => '2️⃣', 'title' => 'Traduz', 'description' => 'Título, slug e SEO por idioma.'],
                        ['icon' => '3️⃣', 'title' => 'Atribui', 'description' => 'Escolhe os mercados onde aparece.'],
                        ['icon' => '4️⃣', 'title' => 'Publica', 'description' => 'Cache, CDN e sitemaps instantaneamente.'],
                    ]],
                    'de' => ['title' => 'Der Veröffentlichungs-Flow', 'items' => [
                        ['icon' => '1️⃣', 'title' => 'Erstellen', 'description' => 'Die Entität entsteht einmal, mit ihren Sektionen.'],
                        ['icon' => '2️⃣', 'title' => 'Übersetzen', 'description' => 'Titel, Slug und SEO pro Sprache.'],
                        ['icon' => '3️⃣', 'title' => 'Zuweisen', 'description' => 'Wähle die Märkte, in denen sie erscheint.'],
                        ['icon' => '4️⃣', 'title' => 'Veröffentlichen', 'description' => 'Cache, CDN und Sitemaps sofort.'],
                    ]],
                    'zh-CN' => ['title' => '发布流程', 'items' => [
                        ['icon' => '1️⃣', 'title' => '创建', 'description' => '实体一次创建，含其版块。'],
                        ['icon' => '2️⃣', 'title' => '翻译', 'description' => '标题、别名与 SEO 按语言。'],
                        ['icon' => '3️⃣', 'title' => '分配', 'description' => '选择它出现的市场。'],
                        ['icon' => '4️⃣', 'title' => '发布', 'description' => '缓存、CDN 与站点地图即时生效。'],
                    ]],
                    'ar' => ['title' => 'مسار النشر', 'items' => [
                        ['icon' => '1️⃣', 'title' => 'أنشئ', 'description' => 'يُولَد الكيان مرة واحدة بأقسامه.'],
                        ['icon' => '2️⃣', 'title' => 'ترجم', 'description' => 'العنوان والرابط وSEO لكل لغة.'],
                        ['icon' => '3️⃣', 'title' => 'خصّص', 'description' => 'اختر الأسواق التي يظهر فيها.'],
                        ['icon' => '4️⃣', 'title' => 'انشر', 'description' => 'الذاكرة المؤقتة وCDN وخرائط الموقع فورًا.'],
                    ]],
                ],
            ],
            [
                'type' => 'before_after',
                'styling' => ['container_style' => 'cyber-card', 'animation' => 'zoomIn', 'animation_delay' => '0.3s'],
                'translations' => [
                    'es' => ['title' => 'Un cambio medible', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'ANTES: N despliegues', 'after_label' => 'DESPUÉS: dbCMS'],
                    'en' => ['title' => 'A measurable change', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'BEFORE: N deploys', 'after_label' => 'AFTER: dbCMS'],
                    'fr' => ['title' => 'Un changement mesurable', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'AVANT : N déploiements', 'after_label' => 'APRÈS : dbCMS'],
                    'it' => ['title' => 'Un cambiamento misurabile', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'PRIMA: N deploy', 'after_label' => 'DOPO: dbCMS'],
                    'pt' => ['title' => 'Uma mudança mensurável', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'ANTES: N deploys', 'after_label' => 'DEPOIS: dbCMS'],
                    'de' => ['title' => 'Ein messbarer Unterschied', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'VORHER: N Deploys', 'after_label' => 'NACHHER: dbCMS'],
                    'zh-CN' => ['title' => '可衡量的改变', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => '之前：多次部署', 'after_label' => '之后：dbCMS'],
                    'ar' => ['title' => 'تغيير قابل للقياس', 'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80', 'after_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1000&q=80', 'before_label' => 'قبل: عمليات نشر متعددة', 'after_label' => 'بعد: dbCMS'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{template: string, translations: array<string, array{title: string, slug: string, meta_title: string, meta_description: string}>, sections: list<array{type: string, styling?: array<string, mixed>, translations: array<string, array<string, mixed>>}>, markets?: list<int>}>
     */
    protected function pages(string $brand): array
    {
        return [
            [
                'template' => 'home',
                'translations' => [
                    'es' => ['title' => 'Inicio', 'slug' => 'home', 'meta_title' => "{$brand}: CMS modular multi-mercado", 'meta_description' => 'Una sola base de datos, tantos mercados e idiomas como necesites.'],
                    'en' => ['title' => 'Home', 'slug' => 'home', 'meta_title' => "{$brand}: multi-market modular CMS", 'meta_description' => 'One database, as many markets and languages as you need.'],
                    'fr' => ['title' => 'Accueil', 'slug' => 'accueil', 'meta_title' => "{$brand}: CMS modulaire multi-marchés", 'meta_description' => 'Une seule base de données, autant de marchés et de langues que nécessaire.'],
                    'it' => ['title' => 'Home', 'slug' => 'home', 'meta_title' => "{$brand}: CMS modulare multi-mercato", 'meta_description' => 'Un solo database, tutti i mercati e le lingue che ti servono.'],
                    'pt' => ['title' => 'Início', 'slug' => 'inicio', 'meta_title' => "{$brand}: CMS modular multi-mercado", 'meta_description' => 'Uma única base de dados, tantos mercados e idiomas como precisares.'],
                    'de' => ['title' => 'Startseite', 'slug' => 'startseite', 'meta_title' => "{$brand}: modularer Multi-Markt-CMS", 'meta_description' => 'Eine Datenbank, so viele Märkte und Sprachen wie nötig.'],
                    'zh-CN' => ['title' => '首页', 'slug' => 'home', 'meta_title' => "{$brand}：多市场模块化 CMS", 'meta_description' => '一个数据库，支持任意多个市场和语言。'],
                    'ar' => ['title' => 'الرئيسية', 'slug' => 'home', 'meta_title' => "{$brand}: نظام إدارة محتوى متعدد الأسواق", 'meta_description' => 'قاعدة بيانات واحدة، وأسواق ولغات بلا حدود.'],
                ],
                'sections' => [
                    [
                        'type' => 'hero',
                        'styling' => ['is_fullwidth' => true, 'bg_color' => '#0A0A0A'],
                        'translations' => [
                            'es' => ['title' => 'Un CMS para publicar en todos tus mercados e idiomas', 'content' => 'Una sola base de datos · Contenido por idioma · Visibilidad por mercado', 'cta_text' => 'Ver los módulos', 'cta_url' => '/modulos'],
                            'en' => ['title' => 'A CMS to publish in every market and language', 'content' => 'One database · Content per language · Visibility per market', 'cta_text' => 'Explore the modules', 'cta_url' => '/modulos'],
                            'fr' => ['title' => 'Un CMS pour publier sur tous vos marchés et langues', 'content' => 'Une seule base de données · Contenu par langue · Visibilité par marché', 'cta_text' => 'Voir les modules', 'cta_url' => '/modulos'],
                            'it' => ['title' => 'Un CMS per pubblicare in tutti i tuoi mercati e lingue', 'content' => 'Un solo database · Contenuti per lingua · Visibilità per mercato', 'cta_text' => 'Scopri i moduli', 'cta_url' => '/modulos'],
                            'pt' => ['title' => 'Um CMS para publicar em todos os teus mercados e idiomas', 'content' => 'Uma única base de dados · Conteúdo por idioma · Visibilidade por mercado', 'cta_text' => 'Ver os módulos', 'cta_url' => '/modulos'],
                            'de' => ['title' => 'Ein CMS für alle deine Märkte und Sprachen', 'content' => 'Eine Datenbank · Inhalte pro Sprache · Sichtbarkeit pro Markt', 'cta_text' => 'Module entdecken', 'cta_url' => '/modulos'],
                            'zh-CN' => ['title' => '面向所有市场和语言的 CMS', 'content' => '一个数据库 · 按语言管理内容 · 按市场控制可见性', 'cta_text' => '查看模块', 'cta_url' => '/modulos'],
                            'ar' => ['title' => 'نظام إدارة محتوى للنشر في كل الأسواق واللغات', 'content' => 'قاعدة بيانات واحدة · محتوى لكل لغة · ظهور لكل سوق', 'cta_text' => 'استعرض الوحدات', 'cta_url' => '/modulos'],
                        ],
                    ],
                    [
                        'type' => 'features',
                        'styling' => ['container_style' => 'cyber-card', 'grid_columns' => '3'],
                        'translations' => [
                            'es' => ['title' => 'Lo que hace la plataforma', 'items' => [
                                ['icon' => '🌍', 'title' => 'Multi-mercado de verdad', 'description' => 'Un contenido existe una vez y se publica en los mercados que elijas.'],
                                ['icon' => '🗣️', 'title' => 'Contenido por idioma', 'description' => 'Título, slug, secciones y SEO se editan por idioma, cada uno con lo suyo.'],
                                ['icon' => '🤖', 'title' => 'Listo para agentes de IA', 'description' => 'GEO y conexión MCP con permisos acotados y auditoría.'],
                            ]],
                            'en' => ['title' => 'What the platform does', 'items' => [
                                ['icon' => '🌍', 'title' => 'Real multi-market', 'description' => 'Content exists once and is published to the markets you pick.'],
                                ['icon' => '🗣️', 'title' => 'Content per language', 'description' => 'Title, slug, sections and SEO are edited per language, each with its own.'],
                                ['icon' => '🤖', 'title' => 'Ready for AI agents', 'description' => 'GEO and MCP connectivity with scoped permissions and audit.'],
                            ]],
                            'fr' => ['title' => 'Ce que fait la plateforme', 'items' => [
                                ['icon' => '🌍', 'title' => 'Véritable multi-marché', 'description' => 'Le contenu existe une fois et se publie sur les marchés choisis.'],
                                ['icon' => '🗣️', 'title' => 'Contenu par langue', 'description' => 'Titre, slug, sections et SEO par langue.'],
                                ['icon' => '🤖', 'title' => 'Prêt pour les agents IA', 'description' => 'GEO et MCP avec permissions limitées et audit.'],
                            ]],
                            'it' => ['title' => 'Cosa fa la piattaforma', 'items' => [
                                ['icon' => '🌍', 'title' => 'Multi-mercato reale', 'description' => 'Il contenuto esiste una volta e si pubblica nei mercati scelti.'],
                                ['icon' => '🗣️', 'title' => 'Contenuti per lingua', 'description' => 'Titolo, slug, sezioni e SEO per lingua.'],
                                ['icon' => '🤖', 'title' => 'Pronto per gli agenti IA', 'description' => 'GEO e MCP con permessi limitati e audit.'],
                            ]],
                            'pt' => ['title' => 'O que a plataforma faz', 'items' => [
                                ['icon' => '🌍', 'title' => 'Multi-mercado real', 'description' => 'O conteúdo existe uma vez e publica-se nos mercados escolhidos.'],
                                ['icon' => '🗣️', 'title' => 'Conteúdo por idioma', 'description' => 'Título, slug, secções e SEO por idioma.'],
                                ['icon' => '🤖', 'title' => 'Pronto para agentes de IA', 'description' => 'GEO e MCP com permissões limitadas e auditoria.'],
                            ]],
                            'de' => ['title' => 'Was die Plattform leistet', 'items' => [
                                ['icon' => '🌍', 'title' => 'Echtes Multi-Markt', 'description' => 'Inhalte existieren einmal und werden den Märkten zugewiesen.'],
                                ['icon' => '🗣️', 'title' => 'Inhalte pro Sprache', 'description' => 'Titel, Slug, Abschnitte und SEO pro Sprache.'],
                                ['icon' => '🤖', 'title' => 'Bereit für KI-Agenten', 'description' => 'GEO und MCP mit eingeschränkten Rechten und Audit.'],
                            ]],
                            'zh-CN' => ['title' => '平台能力', 'items' => [
                                ['icon' => '🌍', 'title' => '真正的多市场', 'description' => '内容只需创建一次，即可发布到指定市场。'],
                                ['icon' => '🗣️', 'title' => '按语言管理内容', 'description' => '标题、别名、区块与 SEO 均按语言单独编辑。'],
                                ['icon' => '🤖', 'title' => '面向 AI 代理', 'description' => 'GEO 与 MCP 连接，权限受限并可审计。'],
                            ]],
                            'ar' => ['title' => 'ما تقدمه المنصة', 'items' => [
                                ['icon' => '🌍', 'title' => 'تعدد أسواق حقيقي', 'description' => 'المحتوى يُنشأ مرة واحدة ويُنشر في الأسواق التي تختارها.'],
                                ['icon' => '🗣️', 'title' => 'محتوى لكل لغة', 'description' => 'العنوان والرابط والأقسام وSEO لكل لغة على حدة.'],
                                ['icon' => '🤖', 'title' => 'جاهز لوكلاء الذكاء الاصطناعي', 'description' => 'GEO واتصال MCP بصلاحيات محدودة وتدقيق.'],
                            ]],
                        ],
                    ],
                    ...$this->homeEnrichmentSections(),
                ],
            ],
            [
                'template' => 'default',
                'translations' => [
                    'es' => ['title' => 'Módulos de la plataforma', 'slug' => 'modulos', 'meta_title' => 'Módulos', 'meta_description' => 'Un recorrido por cada módulo: páginas, blog, tienda, galería, formularios, búsqueda, MCP y agentes.'],
                    'en' => ['title' => 'Platform modules', 'slug' => 'modules', 'meta_title' => 'Modules', 'meta_description' => 'A tour of every module: pages, blog, shop, gallery, forms, search, MCP and agents.'],
                    'fr' => ['title' => 'Modules de la plateforme', 'slug' => 'modules', 'meta_title' => 'Modules', 'meta_description' => 'Un tour de chaque module : pages, blog, boutique, galerie, formulaires, recherche, MCP et agents.'],
                    'it' => ['title' => 'Moduli della piattaforma', 'slug' => 'moduli', 'meta_title' => 'Moduli', 'meta_description' => 'Un tour di ogni modulo: pagine, blog, negozio, galleria, moduli, ricerca, MCP e agenti.'],
                    'pt' => ['title' => 'Módulos da plataforma', 'slug' => 'modulos', 'meta_title' => 'Módulos', 'meta_description' => 'Um percurso por cada módulo: páginas, blogue, loja, galeria, formulários, pesquisa, MCP e agentes.'],
                    'de' => ['title' => 'Module der Plattform', 'slug' => 'module', 'meta_title' => 'Module', 'meta_description' => 'Eine Tour durch jedes Modul: Seiten, Blog, Shop, Galerie, Formulare, Suche, MCP und Agenten.'],
                    'zh-CN' => ['title' => '平台模块', 'slug' => 'modules', 'meta_title' => '模块', 'meta_description' => '浏览每个模块：页面、博客、商店、图库、表单、搜索、MCP 与代理。'],
                    'ar' => ['title' => 'وحدات المنصة', 'slug' => 'modulos', 'meta_title' => 'الوحدات', 'meta_description' => 'جولة في كل وحدة: الصفحات والمدونة والمتجر والمعرض والنماذج والبحث وMCP والوكلاء.'],
                ],
                'sections' => [
                    [
                        'type' => 'text',
                        'translations' => [
                            'es' => ['title' => 'Un módulo por cada necesidad', 'content' => "Cada capacidad de dbCMS es un **módulo desacoplado**. Actívalo o desactívalo sin tocar el resto del sistema.\n\n- **Páginas**: secciones, columnas, fondos y animaciones.\n- **Blog**: artículos con taxonomías y comentarios.\n- **Tienda**: catálogo con precios por mercado.\n- **Galería**: álbumes multimedia localizados.\n- **Formularios**: contacto con antispam y buzón.\n- **Búsqueda**: buscador con URL amigable.\n- **MCP/IA**: asistentes con permisos acotados."],
                            'en' => ['title' => 'One module for every need', 'content' => "Every dbCMS capability is a **decoupled module**. Enable or disable it without touching the rest of the system.\n\n- **Pages**: sections, columns, backgrounds and animations.\n- **Blog**: articles with taxonomies and comments.\n- **Shop**: catalog with per-market pricing.\n- **Gallery**: localized media albums.\n- **Forms**: contact with antispam and inbox.\n- **Search**: friendly-URL search.\n- **MCP/AI**: assistants with scoped permissions."],
                            'fr' => ['title' => 'Un module pour chaque besoin', 'content' => "Chaque capacité de dbCMS est un **module découplé**. Activez-le ou désactivez-le sans toucher au reste du système.\n\n- **Pages** : sections, colonnes, arrière-plans et animations.\n- **Blog** : articles avec taxonomies et commentaires.\n- **Boutique** : catalogue avec prix par marché.\n- **Galerie** : albums multimédia localisés.\n- **Formulaires** : contact avec antispam et boîte de réception.\n- **Recherche** : recherche à URL conviviale.\n- **MCP/IA** : assistants avec permissions limitées."],
                            'it' => ['title' => 'Un modulo per ogni esigenza', 'content' => "Ogni capacità di dbCMS è un **modulo disaccoppiato**. Attivalo o disattivalo senza toccare il resto del sistema.\n\n- **Pagine**: sezioni, colonne, sfondi e animazioni.\n- **Blog**: articoli con tassonomie e commenti.\n- **Negozio**: catalogo con prezzi per mercato.\n- **Galleria**: album multimediali localizzati.\n- **Moduli**: contatto con antispam e casella di posta.\n- **Ricerca**: ricerca con URL amichevole.\n- **MCP/IA**: assistenti con permessi limitati."],
                            'pt' => ['title' => 'Um módulo para cada necessidade', 'content' => "Cada capacidade do dbCMS é um **módulo desacoplado**. Ativa-o ou desativa-o sem tocar no resto do sistema.\n\n- **Páginas**: secções, colunas, fundos e animações.\n- **Blogue**: artigos com taxonomias e comentários.\n- **Loja**: catálogo com preços por mercado.\n- **Galeria**: álbuns multimédia localizados.\n- **Formulários**: contacto com antispam e caixa de entrada.\n- **Pesquisa**: pesquisa com URL amigável.\n- **MCP/IA**: assistentes com permissões limitadas."],
                            'de' => ['title' => 'Ein Modul für jeden Bedarf', 'content' => "Jede dbCMS-Fähigkeit ist ein **entkoppeltes Modul**. Aktiviere oder deaktiviere es, ohne den Rest des Systems anzufassen.\n\n- **Seiten**: Sektionen, Spalten, Hintergründe und Animationen.\n- **Blog**: Artikel mit Taxonomien und Kommentaren.\n- **Shop**: Katalog mit Preisen pro Markt.\n- **Galerie**: lokalisierte Medienalben.\n- **Formulare**: Kontakt mit Antispam und Postfach.\n- **Suche**: Suche mit sprechender URL.\n- **MCP/KI**: Assistenten mit eingeschränkten Rechten."],
                            'zh-CN' => ['title' => '按需启用模块', 'content' => "dbCMS 的每一项能力都是**解耦模块**，可随时启用或停用，不影响系统其他部分。\n\n- **页面**：区块、分栏、背景与动画。\n- **博客**：带分类与评论的文章。\n- **商店**：按市场定价的目录。\n- **图库**：本地化媒体相册。\n- **表单**：带防垃圾与收件箱。\n- **搜索**：友好 URL 搜索。\n- **MCP/AI**：权限受限的助手。".
                                "\n\n请随意切换语言：本页展示同一内容如何以多语言、多市场呈现。"],
                            'ar' => ['title' => 'وحدة لكل احتياج', 'content' => "كل قدرة في dbCMS هي **وحدة مستقلة**، يمكنك تفعيلها أو تعطيلها دون التأثير على بقية النظام.\n\n- **الصفحات**: أقسام وأعمدة وخلفيات وحركات.\n- **المدونة**: مقالات مع تصنيفات وتعليقات.\n- **المتجر**: كتالوج بأسعار لكل سوق.\n- **المعرض**: ألبومات وسائط مترجمة.\n- **النماذج**: تواصل مع مكافحة الرسائل المزعجة.\n- **البحث**: بحث بروابط صديقة.\n- **MCP/الذكاء الاصطناعي**: مساعدون بصلاحيات محدودة."],
                        ],
                    ],
                ],
            ],
            [
                'template' => 'default',
                'translations' => [
                    'es' => ['title' => 'Cómo funciona', 'slug' => 'como-funciona', 'meta_title' => 'Cómo funciona', 'meta_description' => 'El modelo de tres capas: entidad, traducción y mercado, explicado en una página.'],
                    'en' => ['title' => 'How it works', 'slug' => 'how-it-works', 'meta_title' => 'How it works', 'meta_description' => 'The three-layer model: entity, translation and market, explained on one page.'],
                    'fr' => ['title' => 'Comment ça marche', 'slug' => 'comment-ca-marche', 'meta_title' => 'Comment ça marche', 'meta_description' => 'Le modèle à trois couches : entité, traduction et marché, expliqué sur une page.'],
                    'it' => ['title' => 'Come funziona', 'slug' => 'come-funziona', 'meta_title' => 'Come funziona', 'meta_description' => 'Il modello a tre livelli: entità, traduzione e mercato, spiegato in una pagina.'],
                    'pt' => ['title' => 'Como funciona', 'slug' => 'como-funciona', 'meta_title' => 'Como funciona', 'meta_description' => 'O modelo de três camadas: entidade, tradução e mercado, explicado numa página.'],
                    'de' => ['title' => 'So funktioniert es', 'slug' => 'so-funktioniert-es', 'meta_title' => 'So funktioniert es', 'meta_description' => 'Das Drei-Ebenen-Modell: Entität, Übersetzung und Markt, auf einer Seite erklärt.'],
                    'zh-CN' => ['title' => '工作原理', 'slug' => 'how-it-works', 'meta_title' => '工作原理', 'meta_description' => '三层模型：实体、翻译与市场，一页讲清。'],
                    'ar' => ['title' => 'كيف تعمل المنصة', 'slug' => 'como-funciona', 'meta_title' => 'كيف تعمل المنصة', 'meta_description' => 'النموذج ثلاثي الطبقات: الكيان والترجمة والسوق، مشروح في صفحة واحدة.'],
                ],
                'sections' => $this->howItWorksSections(),
            ],
            [
                'template' => 'default',
                'translations' => [
                    'es' => ['title' => 'Bloques y componentes', 'slug' => 'bloques', 'meta_title' => 'Bloques y componentes', 'meta_description' => 'Todos los bloques del CMS en acción: hero, kpi, pricing, faq, testimonios, carrusel, gráficos y más.'],
                    'en' => ['title' => 'Blocks & components', 'slug' => 'blocks', 'meta_title' => 'Blocks & components', 'meta_description' => 'Every CMS block in action: hero, kpi, pricing, faq, testimonials, carousel, charts and more.'],
                    'fr' => ['title' => 'Blocs et composants', 'slug' => 'blocs', 'meta_title' => 'Blocs et composants', 'meta_description' => 'Tous les blocs du CMS en action : hero, kpi, tarifs, faq, témoignages, carrousel, graphiques et plus.'],
                    'it' => ['title' => 'Blocchi e componenti', 'slug' => 'blocchi', 'meta_title' => 'Blocchi e componenti', 'meta_description' => 'Tutti i blocchi del CMS in azione: hero, kpi, prezzi, faq, testimonianze, carosello, grafici e altro.'],
                    'pt' => ['title' => 'Blocos e componentes', 'slug' => 'blocos', 'meta_title' => 'Blocos e componentes', 'meta_description' => 'Todos os blocos do CMS em ação: hero, kpi, preços, faq, testemunhos, carrossel, gráficos e mais.'],
                    'de' => ['title' => 'Blöcke und Komponenten', 'slug' => 'bloecke', 'meta_title' => 'Blöcke und Komponenten', 'meta_description' => 'Alle CMS-Blöcke in Aktion: Hero, KPI, Preise, FAQ, Testimonials, Karussell, Diagramme und mehr.'],
                    'zh-CN' => ['title' => '区块与组件', 'slug' => 'blocks', 'meta_title' => '区块与组件', 'meta_description' => '所有 CMS 区块实操展示：主视觉、指标、定价、FAQ、证言、轮播、图表等。'],
                    'ar' => ['title' => 'الوحدات والمكونات', 'slug' => 'bloques', 'meta_title' => 'الوحدات والمكونات', 'meta_description' => 'جميع وحدات نظام إدارة المحتوى قيد التشغيل: الواجهة والمؤشرات والأسعار والأسئلة والشهادات والعرض الدوار والرسوم البيانية.'],
                ],
                'sections' => [...$this->showcaseSectionsA(), ...$this->showcaseSectionsB()],
            ],
            [
                'template' => 'default',
                'translations' => [
                    'es' => ['title' => 'Contacto', 'slug' => 'contacto', 'meta_title' => 'Contacto', 'meta_description' => 'Cuéntanos qué necesitas.'],
                    'en' => ['title' => 'Contact', 'slug' => 'contact', 'meta_title' => 'Contact', 'meta_description' => 'Tell us what you need.'],
                ],
                'sections' => [
                    [
                        'type' => 'text',
                        'translations' => [
                            'es' => ['title' => 'Hablemos', 'content' => 'Rellena el formulario y te responderemos en menos de 24 horas.'],
                            'en' => ['title' => 'Let’s talk', 'content' => 'Fill in the form and we will reply within 24 hours.'],
                        ],
                    ],
                    [
                        'type' => 'widget_form',
                        'styling' => ['container_style' => 'cyber-card'],
                        'translations' => [
                            'es' => ['title' => 'Formulario de contacto', 'form_slug' => 'contacto'],
                            'en' => ['title' => 'Contact form', 'form_slug' => 'contacto'],
                        ],
                    ],
                ],
            ],
            [
                'template' => 'default',
                'translations' => [
                    'es' => ['title' => 'Política de privacidad', 'slug' => 'privacidad', 'meta_title' => 'Política de privacidad', 'meta_description' => 'Cómo tratamos tus datos.'],
                    'en' => ['title' => 'Privacy Policy', 'slug' => 'privacy', 'meta_title' => 'Privacy Policy', 'meta_description' => 'How we handle your data.'],
                ],
                'sections' => [
                    [
                        'type' => 'text',
                        'translations' => [
                            'es' => ['title' => 'Política de privacidad', 'content' => 'Texto de ejemplo para la política de privacidad.'],
                            'en' => ['title' => 'Privacy Policy', 'content' => 'Sample text for the privacy policy.'],
                        ],
                    ],
                ],
            ],
            [
                'template' => 'default',
                'translations' => [
                    'es' => ['title' => 'Términos de servicio', 'slug' => 'terminos', 'meta_title' => 'Términos de servicio', 'meta_description' => 'Condiciones de uso.'],
                    'en' => ['title' => 'Terms of Service', 'slug' => 'terms', 'meta_title' => 'Terms of Service', 'meta_description' => 'Terms of use.'],
                ],
                'sections' => [
                    [
                        'type' => 'text',
                        'translations' => [
                            'es' => ['title' => 'Términos de servicio', 'content' => 'Texto de ejemplo para los términos de servicio.'],
                            'en' => ['title' => 'Terms of Service', 'content' => 'Sample text for the terms of service.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
