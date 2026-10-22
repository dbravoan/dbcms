<?php

declare(strict_types=1);

namespace Modules\Gallery\Http\Controllers;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Gallery\Contracts\GalleryFrontPageServiceInterface;
use Modules\Gallery\DTOs\GalleryFrontInput;

/** Compatibility transport for the established gallery routes. */
final class GalleryFrontController extends Controller
{
    public function __construct(private readonly GalleryFrontPageServiceInterface $pages, private readonly LocalizationServiceInterface $localization) {}

    public function index(Request $request): View
    {
        $data = $this->pages->index(GalleryFrontInput::fromRequest($request, $this->localization));
        abort_if($data->viewName === null, 404);

        return view($data->viewName, $data->data);
    }

    public function show(Request $request): View
    {
        $data = $this->pages->show(GalleryFrontInput::fromRequest($request, $this->localization));
        abort_if($data->viewName === null, 404);

        return view($data->viewName, $data->data);
    }
}
