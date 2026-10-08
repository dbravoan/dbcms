<?php

declare(strict_types=1);

namespace Modules\Gallery\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Gallery\Contracts\GalleryEditorialServiceInterface;

final class GalleryPostController extends Controller
{
    public function __invoke(Request $request, GalleryEditorialServiceInterface $service): RedirectResponse
    {
        $gallery = $service->create($request->all());

        return redirect()->route('admin.galleries.index')->with('success', "Álbum {$gallery->title} creado exitosamente.");
    }
}
