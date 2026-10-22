<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditorialEntityRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Contracts\PostDomainServiceInterface;

final class PostPostController extends Controller
{
    public function __construct(private readonly PostDomainServiceInterface $service) {}

    public function __invoke(EditorialEntityRequest $request): RedirectResponse
    {
        $this->service->createPost($request->validated(), $request->user());

        return redirect()->route('admin.blog.posts.index')->with('success', 'Artículo creado correctamente.');
    }
}
