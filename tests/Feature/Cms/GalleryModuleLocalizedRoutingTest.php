<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Gallery\Blocks\GalleryAlbumBlock;
use Modules\Gallery\GalleryModuleServiceProvider;
use Modules\Gallery\Models\Gallery;
use Tests\Support\CmsFixtures;
use Tests\Support\GalleryFixtures;
use Tests\Support\GalleryRoutingComposition;

uses(RefreshDatabase::class, GalleryRoutingComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en', 'fr'], ['eu', 'us']);
    GalleryFixtures::album();
});

test('gallery module is pageable and exposes standalone localized routes', function (): void {
    $provider = new GalleryModuleServiceProvider(app());

    expect($provider->isPageable())->toBeTrue();
});

test('standalone gallery routes render in every locale', function (): void {
    $this->get('/eu/es/galeria')->assertOk();
    $this->get('/eu/en/gallery')->assertOk();
    $this->get('/eu/fr/galerie')->assertOk();

    // Localized item slug resolution.
    $this->get('/eu/es/galeria/recorrido-plataforma')->assertOk();
    $this->get('/eu/en/gallery/platform-tour')->assertOk();
});

test('gallery album can be embedded via GalleryAlbumBlock into modular pages', function (): void {
    $gallery = Gallery::first();
    expect($gallery)->not->toBeNull();

    $transEs = $gallery->translations->where('locale', 'es')->first();
    expect($transEs)->not->toBeNull();

    $block = new GalleryAlbumBlock;
    $renderedHtml = $block->render([
        'album_id' => $gallery->id,
        'title' => 'Nuestra Galería Modular',
        'layout' => 'grid',
        'columns' => 3,
    ]);

    expect((string) $renderedHtml)->toContain('Nuestra Galería Modular');
});
