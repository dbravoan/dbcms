<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\ModuleSettings;

use App\Contracts\Services\ModuleSettingsAdministrationInterface;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class ModuleSettingsGetController extends Controller
{
    public function __construct(private readonly ModuleSettingsAdministrationInterface $service) {}

    public function __invoke(string $identifier): View
    {
        $data = $this->service->view($identifier);

        return view($data->view, $data->data);
    }
}
