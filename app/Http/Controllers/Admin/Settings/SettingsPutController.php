<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteSettingsUpdateRequest;
use App\Services\Cms\SiteSettingsService;
use Illuminate\Http\RedirectResponse;

final class SettingsPutController extends Controller
{
    public function __invoke(SiteSettingsUpdateRequest $request, SiteSettingsService $settings): RedirectResponse
    {
        $settings->update($request->settings());

        return redirect()->route('admin.settings.index')
            ->with('success', 'Ajustes del sitio y empresa actualizados correctamente.');
    }
}
