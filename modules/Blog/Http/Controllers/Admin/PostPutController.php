<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditorialEntityRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Models\Post;

final class PostPutController extends Controller
{
    public function __construct(private readonly PostDomainServiceInterface $service) {}

    public function __invoke(EditorialEntityRequest $request, Post $post): RedirectResponse
    {
        $this->service->updatePost($post, $request->validated());

        return redirect()->route('admin.blog.posts.index')->with('success', 'Artículo actualizado correctamente.');
    }
}
