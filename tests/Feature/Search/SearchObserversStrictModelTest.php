<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Models\GalleryItem;
use Modules\Gallery\Models\GalleryTranslation;
use Modules\Gallery\Observers\GallerySearchObserver;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageSection;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Models\SectionTranslation;
use Modules\Pages\Observers\PageSearchObserver;

test('page observer resolves the section translation parent with strict missing attributes', function (): void {
    $page = Page::withoutEvents(fn (): Page => Page::create(['is_published' => false, 'template' => 'default']));
    $section = PageSection::withoutEvents(fn (): PageSection => $page->sections()->create(['type' => 'text', 'is_active' => true, 'status' => 'draft', 'order' => 0]));
    $method = new ReflectionMethod(PageSearchObserver::class, 'entityId');
    $previous = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();
    try {
        foreach ([
            new Page,
            new PageTranslation(['page_id' => $page->getKey()]),
            new PageSection(['page_id' => $page->getKey()]),
            new SectionTranslation(['page_section_id' => $section->getKey()]),
        ] as $model) {
            if ($model instanceof Page) {
                $model->setAttribute('id', $page->getKey());
            }
            $model->exists = true;
            expect($method->invoke(new PageSearchObserver, $model))->toBe($page->getKey());
        }
        $partial = new SectionTranslation;
        $partial->exists = true;
        expect($method->invoke(new PageSearchObserver, $partial))->toBeNull();
    } finally {
        Model::preventAccessingMissingAttributes($previous);
    }
});

test('gallery observer resolves only its own real keys with strict missing attributes', function (): void {
    $method = new ReflectionMethod(GallerySearchObserver::class, 'entityId');
    $previous = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();
    try {
        $gallery = new Gallery;
        $gallery->setAttribute('id', 17);
        foreach ([$gallery, new GalleryTranslation(['gallery_id' => 17]), new GalleryItem(['gallery_id' => 17])] as $model) {
            $model->exists = true;
            expect($method->invoke(new GallerySearchObserver, $model))->toBe(17);
        }
        $partial = new GalleryItem;
        $partial->exists = true;
        expect($method->invoke(new GallerySearchObserver, $partial))->toBeNull();
    } finally {
        Model::preventAccessingMissingAttributes($previous);
    }
});
