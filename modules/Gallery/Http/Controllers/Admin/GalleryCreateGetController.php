<?php

declare(strict_types=1);

namespace Modules\Gallery\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Contracts\View\View;

final class GalleryCreateGetController extends Controller
{
    public function __invoke(): View
    {
        $markets = Market::with('languages')->where('is_active', true)->get();
        $languages = Language::where('is_active', true)->get();

        return view('admin.galleries.create', [
            'markets' => $markets,
            'languages' => $languages,
        ]);
    }
}
