<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Blog\Contracts\PostDomainServiceInterface;
use Modules\Blog\Models\Post;

final class PostEditGetController extends Controller
{
    public function __construct(private readonly PostDomainServiceInterface $service) {}

    public function __invoke(Post $post): View
    {
        return view('admin.blog.posts.edit', $this->service->editData($post));
    }
}
