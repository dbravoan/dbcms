<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\IntegrationGuides;

use App\Contracts\Services\IntegrationGuideServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class IntegrationGuidesGetController extends Controller
{
    public function __construct(private readonly IntegrationGuideServiceInterface $service) {}

    public function __invoke(Request $request, string $identifier): View
    {
        return view('admin.integrations.index', $this->service->index($identifier, (int) $request->user()?->getAuthIdentifier()));
    }
}
