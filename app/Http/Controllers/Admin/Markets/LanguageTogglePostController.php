<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Markets;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;

/**
 * Enables/disables a language. The platform default language is protected so
 * the site always keeps at least one usable locale.
 */
final class LanguageTogglePostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(Language $language): RedirectResponse
    {
        return $this->resources->toggle('language', $language);
    }
}
