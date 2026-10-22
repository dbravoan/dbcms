<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Market;
use App\Models\User;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

final class BlogFixtures
{
    /** @param list<string> $locales */
    public static function post(array $locales = ['es', 'en']): Post
    {
        $category = Category::create(['is_active' => true]);
        $post = Post::create(['user_id' => User::where('email', 'admin@db-cms.io')->sole()->id, 'category_id' => $category->id, 'is_published' => true, 'published_at' => now(), 'allow_comments' => true]);
        foreach ($locales as $locale) {
            $category->translations()->create(['locale' => $locale, 'name' => 'Arquitectura', 'slug' => 'arquitectura']);
            $post->translations()->create(['locale' => $locale, 'title' => 'Arquitectura modular', 'slug' => 'arquitectura-modular', 'content' => '<p>Contenido público de arquitectura modular.</p>', 'meta_description' => 'Contenido público de arquitectura modular.']);
        }
        $markets = Market::pluck('id')->all();
        $category->markets()->sync($markets);
        $post->markets()->sync($markets);

        return $post;
    }
}
