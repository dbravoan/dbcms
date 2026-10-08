<?php

declare(strict_types=1);

namespace Modules\Maintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;

final class MaintenancePreviewGetController extends Controller
{
    public function __construct(private readonly MaintenanceSettingsAdministrationInterface $service) {}

    public function __invoke(): Response
    {
        return response($this->service->preview(), 200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-CMS-Maintenance-Preview' => '1', 'Cache-Control' => 'no-cache, no-store, must-revalidate']);
    }
}
