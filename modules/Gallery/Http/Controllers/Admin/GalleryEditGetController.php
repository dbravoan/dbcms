<?php

declare(strict_types=1);

namespace Modules\Gallery\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Gallery\Contracts\GalleryEditorialServiceInterface;
use Modules\Gallery\Models\Gallery;

final class GalleryEditGetController extends Controller
{
    public function __invoke(Gallery $gallery, GalleryEditorialServiceInterface $service): View
    {
        return view('admin.galleries.edit', $service->editData($gallery));
    }
}
