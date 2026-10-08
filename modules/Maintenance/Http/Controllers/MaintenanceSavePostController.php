<?php

declare(strict_types=1);

namespace Modules\Maintenance\Http\Controllers;

use App\DTOs\Modules\ModuleSettingsUpdateDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;

final class MaintenanceSavePostController extends Controller
{
    public function __construct(private readonly MaintenanceSettingsAdministrationInterface $service) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->service->update(new ModuleSettingsUpdateDTO($request->except(['_token', '_method'])));

        return redirect()->route('admin.modules.settings', ['identifier' => 'maintenance'])->with('success', 'Configuración de Mantenimiento & Construcción guardada correctamente.');
    }
}
