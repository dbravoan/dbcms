<?php

declare(strict_types=1);

use App\Content\Pages\Contracts\PagePublicReaderInterface;
use App\Content\Pages\Models\Page;
use App\Contracts\Services\LocalizationServiceInterface;
use App\DTOs\Front\PublicContentContext;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use Modules\Blog\Contracts\BlogPublicReaderInterface;
use Modules\Blog\Models\Post;
use Tests\Support\BlogComposition;

uses(BlogComposition::class);

beforeEach(function (): void {
    $es = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true, 'is_default' => true]);
    $en = Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_active' => true, 'is_default' => true]);
    $this->market->languages()->sync([$es->getKey() => ['is_default' => true], $en->getKey() => ['is_default' => false]]);
    $this->author = User::factory()->create();
});

test('public article and page readers work from immutable context without an HTTP request', function (): void {
    $post = Post::create(['user_id' => $this->author->getKey(), 'is_published' => true, 'published_at' => now()]);
    $post->markets()->sync([$this->market->getKey()]);
    $post->translations()->create(['locale' => 'es', 'title' => 'Artículo nativo', 'slug' => 'articulo-nativo', 'content' => 'Datos contrastados', 'summary' => 'Resumen verificable']);
    $article = app(BlogPublicReaderInterface::class)->show(new PublicContentContext($this->market, 'es', 'articulo-nativo', 'http://localhost/es/es/blog/articulo-nativo', ['es', 'es', 'blog', 'articulo-nativo']));
    expect($article->viewName)->not->toBeNull()->and($article->data['post']->getKey())->toBe($post->getKey())->and($article->data['translation']->locale)->toBe('es');
    $page = Page::create(['is_published' => true, 'published_at' => now()]);
    $page->markets()->sync([$this->market->getKey()]);
    $page->translations()->create(['locale' => 'es', 'title' => 'Página nativa', 'slug' => 'pagina-nativa', 'meta_description' => 'Descripción contrastada']);
    $data = app(PagePublicReaderInterface::class)->show(new PublicContentContext($this->market, 'es', 'pagina-nativa', 'http://localhost/es/es/pagina-nativa', ['es', 'es', 'pagina-nativa']));
    expect($data->viewName)->not->toBeNull()->and($data->data['page']->getKey())->toBe($page->getKey())->and($data->data)->toHaveKey('seoMeta');
    $missing = app(PagePublicReaderInterface::class)->show(new PublicContentContext($this->market, 'es', 'missing-native'));
    expect($missing->viewName)->toBeNull()->and($missing->redirectUrl)->toBeNull();
});

test('localized hierarchical paths are replaced as a whole rather than duplicating ancestors', function (): void {
    $urls = app(LocalizationServiceInterface::class)->buildAlternateUrlsMapForPath(['es', 'es', 'blog', 'padre', 'hijo'], ['es' => 'padre/hijo', 'en' => 'parent/child']);
    $localization = app(LocalizationServiceInterface::class);
    expect($urls['es']['es'])->toBe($localization->url('/blog/padre/hijo', $this->market, 'es'))
        ->and($urls['es']['en'])->toBe($localization->url('/blog/parent/child', $this->market, 'en'));
});
