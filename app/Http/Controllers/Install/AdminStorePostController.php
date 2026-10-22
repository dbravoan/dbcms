<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Contracts\Services\InstallationAdminServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Install\InstallationAdminRequest;
use Illuminate\Http\RedirectResponse;

final class AdminStorePostController extends Controller
{
    public function __construct(private readonly InstallationAdminServiceInterface $service) {}

    public function __invoke(InstallationAdminRequest $request): RedirectResponse
    {
        $data = $request->toDto();
        $this->service->finalize($data);

        return redirect()->route('install.step5')->with('install.brand', $data->brand)->with('install.just_installed', true);
    }
}
