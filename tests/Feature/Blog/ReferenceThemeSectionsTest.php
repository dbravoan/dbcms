<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\Support\BlogComposition;
use Tests\Support\BlogFixtures;
use Tests\Support\CmsFixtures;

uses(BlogComposition::class);

test('reference and freshly generated themes render published blog sections', function (bool $generated): void {
    $this->withoutVite();
    CmsFixtures::localization(['es'], ['es']);
    CmsFixtures::identities();
    $post = BlogFixtures::post(['es']);
    $section = $post->sections()->create(['type' => 'text', 'status' => 'published', 'is_active' => true, 'order' => 0]);
    $section->translations()->create(['locale' => 'es', 'content_json' => ['type' => 'text', 'content' => 'BLOG SECCION PUBLICADA']]);
    $id = 'journey-blog-'.strtolower(str()->random(8));
    try {
        if ($generated) {
            $this->artisan('make:front-theme', ['id' => $id])->assertSuccessful();
        }
        config(['cms.theme' => $generated ? $id : 'default-clean', 'cms.page_cache.enabled' => false]);
        $this->get('/es/es/blog/arquitectura-modular')->assertOk()->assertSee('BLOG SECCION PUBLICADA');
    } finally {
        if ($generated) {
            File::deleteDirectory(base_path('themes/'.$id));
        }
    }
})->with([false, true]);
