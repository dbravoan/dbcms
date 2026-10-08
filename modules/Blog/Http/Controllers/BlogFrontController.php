<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Http\Adapters\FrontPageResponseMapper;
use App\Http\Adapters\PublicContentContextFactory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Blog\Contracts\BlogPublicReaderInterface;
use Modules\Blog\Contracts\CommentPublicationServiceInterface;
use Modules\Blog\Http\Adapters\CommentResponseMapper;
use Modules\Blog\Http\Requests\CommentSubmissionRequest;
use Modules\Blog\Models\Category;

final class BlogFrontController extends Controller
{
    public function __construct(private readonly BlogPublicReaderInterface $reader, private readonly PublicContentContextFactory $contexts, private readonly FrontPageResponseMapper $responses, private readonly CommentPublicationServiceInterface $comments, private readonly CommentResponseMapper $commentResponses) {}

    public function index(Request $request): View|RedirectResponse
    {
        return $this->responses->respond($this->reader->index($this->contexts->fromRequest($request)));
    }

    public function show(Request $request): View
    {
        return $this->responses->view($this->reader->show($this->contexts->fromRequest($request)));
    }

    public function resolveSlug(Request $request): View|RedirectResponse
    {
        return $this->responses->respond($this->reader->resolveSlug($this->contexts->fromRequest($request)));
    }

    public function category(Request $request, ?Category $category = null): View|RedirectResponse
    {
        return $this->responses->respond($this->reader->category($this->contexts->fromRequest($request), $category));
    }

    public function storeComment(CommentSubmissionRequest $request): RedirectResponse
    {
        return $this->commentResponses->respond($this->comments->submit($this->contexts->fromRequest($request), $request->submission()));
    }
}
