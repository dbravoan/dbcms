<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\SocialPublishing\Services\SocialPresentation;

final class PublicationPreviewGetController extends Controller
{
    public function __invoke(string $publication, SocialPresentation $presentation): View
    {
        return view('social-publishing::admin.preview', $presentation->preview($publication));
    }
}
