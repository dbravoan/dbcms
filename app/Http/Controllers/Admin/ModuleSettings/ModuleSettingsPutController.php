<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\ModuleSettings;

use App\Contracts\Services\ModuleSettingsAdministrationInterface;
use App\DTOs\Modules\ModuleSettingsUpdateDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ModuleSettingsPutController extends Controller
{
    public function __construct(private readonly ModuleSettingsAdministrationInterface $service) {}

    public function __invoke(Request $request, string $identifier): RedirectResponse
    {
        $name = $this->service->update($identifier, new ModuleSettingsUpdateDTO($request->except(['_token', '_method'])));

        return redirect()->route('admin.modules.settings', ['identifier' => $identifier])->with('success', "Configuración del módulo '{$name}' guardada correctamente.");
    }
}
