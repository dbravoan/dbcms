<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Models\Post;

final class PostDeleteController extends Controller
{
    public function __construct(private readonly PostDomainServiceInterface $service) {}

    public function __invoke(Post $post): RedirectResponse
    {
        $this->service->deletePost($post);

        return redirect()->route('admin.blog.posts.index')->with('success', 'Artículo eliminado correctamente.');
    }
}
