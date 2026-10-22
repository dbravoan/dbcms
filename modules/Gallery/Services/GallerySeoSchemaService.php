<?php

declare(strict_types=1);

namespace Modules\Gallery\Services;

use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Models\GalleryTranslation;

/** Describes the album and existing visible items without invented ratings. */
final class GallerySeoSchemaService
{
    /** @return array<string, mixed> */
    public function entity(Gallery $gallery, GalleryTranslation $translation): array
    {
        $entity = ['@type' => 'ImageGallery', 'name' => $translation->title];
        if ($translation->description) {
            $entity['description'] = strip_tags($translation->description);
        }
        $images = [];
        foreach ($gallery->items as $item) {
            if ($item->image_url === '') {
                continue;
            }
            $image = ['@type' => 'ImageObject', 'contentUrl' => url($item->image_url)];
            if ($item->title) {
                $image['name'] = $item->title;
            }
            if ($item->caption ?: $item->alt_text) {
                $image['caption'] = $item->caption ?: $item->alt_text;
            }
            $images[] = $image;
        }
        if ($images !== []) {
            $entity['image'] = $images;
        }

        return $entity;
    }
}
