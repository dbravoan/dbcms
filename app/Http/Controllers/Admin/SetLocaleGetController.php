<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cms\BackofficeLocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Quick locale switch (works for guests on the login screen too).
 */
final class SetLocaleGetController extends Controller
{
    public function __construct(
        private readonly BackofficeLocaleService $locales
    ) {}

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $this->locales->apply($request, $locale);

        return redirect()->back();
    }
}
