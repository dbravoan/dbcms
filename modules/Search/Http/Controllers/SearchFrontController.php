<?php

declare(strict_types=1);

namespace Modules\Search\Http\Controllers;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Search\Contracts\SearchFrontPageServiceInterface;
use Modules\Search\DTOs\SearchFrontInput;

/** Compatibility transport: public search decisions belong to its module service. */
final class SearchFrontController extends Controller
{
    public function __construct(private readonly SearchFrontPageServiceInterface $pages, private readonly LocalizationServiceInterface $localization) {}

    public function hub(Request $request): View|RedirectResponse
    {
        $data = $this->pages->hub(SearchFrontInput::fromRequest($request, $this->localization));
        abort_if($data->viewName === null && $data->redirectUrl === null, 404);

        return $data->redirectUrl !== null ? redirect()->to($data->redirectUrl, $data->redirectStatus) : view($data->viewName, $data->data);
    }

    public function results(Request $request): View|RedirectResponse
    {
        $data = $this->pages->results(SearchFrontInput::fromRequest($request, $this->localization));
        abort_if($data->viewName === null && $data->redirectUrl === null, 404);

        return $data->redirectUrl !== null ? redirect()->to($data->redirectUrl, $data->redirectStatus) : view($data->viewName, $data->data);
    }

    /** @return Collection<int,Language> */
    public function languages(Market $market): Collection
    {
        return $this->pages->languages($market);
    }
}
