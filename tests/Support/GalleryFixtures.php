<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Market;
use Modules\Gallery\Models\Gallery;

final class GalleryFixtures
{
    public static function album(): Gallery
    {
        $gallery = Gallery::create(['is_active' => true, 'title' => 'Galería de prueba', 'slug' => 'recorrido-plataforma']);
        foreach (['es' => 'recorrido-plataforma', 'en' => 'platform-tour', 'fr' => 'visite-plateforme'] as $locale => $slug) {
            $gallery->translations()->create(['locale' => $locale, 'title' => 'Galería '.$locale, 'slug' => $slug, 'description' => 'Una galería pública.']);
        }
        $gallery->markets()->sync(Market::pluck('id')->all());

        return $gallery;
    }
}
