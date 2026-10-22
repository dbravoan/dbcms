<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Markets;

use App\Http\Adapters\Resources\AdminResourceMutationTransport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResourceMutationRequest;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;

/**
 * Updates a language (name, native name, flag emoji, direction, status).
 */
final class LanguagePutController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request, Language $language): RedirectResponse
    {
        return $this->resources->update('language', $language, $request);
    }
}
