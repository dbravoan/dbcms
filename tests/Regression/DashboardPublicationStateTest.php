<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Blade;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostTranslation;

uses(TestCase::class);

it('labels a draft honestly in each administrative dashboard', function (string $template): void {
    app()->setLocale('es');
    $source = file_get_contents(base_path($template));
    preg_match('/@foreach\(\$recentPosts as \$post\)(.*?)@endforeach/s', $source, $match);
    $post = new Post(['is_published' => false]);
    $post->id = 42;
    $post->setRelation('translations', collect([new PostTranslation(['title' => 'Draft fixture', 'locale' => 'es'])]));
    $post->setRelation('author', null);
    $html = Blade::render($match[0], ['recentPosts' => collect([$post])]);
    expect(strip_tags($html))->toContain('Borrador')->not->toContain('Publicado');
})->with([
    'default' => 'resources/views/admin/dashboard.blade.php',
    'dbcms' => 'resources/views/themes/admin/dbcms/admin/dashboard.blade.php',
    'termocms' => 'resources/views/themes/admin/termocms/admin/dashboard.blade.php',
]);
