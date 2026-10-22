<?php

declare(strict_types=1);

namespace Modules\Gallery\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Gallery\Contracts\GalleryEditorialServiceInterface;
use Modules\Gallery\Models\Gallery;

final class GalleryDeleteController extends Controller
{
    public function __invoke(Gallery $gallery, GalleryEditorialServiceInterface $service): RedirectResponse
    {
        $service->delete($gallery);

        return redirect()->route('admin.galleries.index')->with('success', 'Álbum eliminado.');
    }
}
