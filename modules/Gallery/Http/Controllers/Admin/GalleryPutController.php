<?php

declare(strict_types=1);

namespace Modules\Gallery\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Gallery\Contracts\GalleryEditorialServiceInterface;
use Modules\Gallery\Models\Gallery;

final class GalleryPutController extends Controller
{
    public function __invoke(Request $request, Gallery $gallery, GalleryEditorialServiceInterface $service): RedirectResponse
    {
        $service->update($gallery, $request->all());

        return redirect()->route('admin.galleries.index')->with('success', 'Álbum actualizado correctamente.');
    }
}
