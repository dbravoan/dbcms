<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Contracts\Services\InstallationDatabaseServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Install\DatabaseSetupRequest;
use Illuminate\Http\RedirectResponse;

final class DatabaseSavePostController extends Controller
{
    public function __construct(private readonly InstallationDatabaseServiceInterface $service) {}

    public function __invoke(DatabaseSetupRequest $request): RedirectResponse
    {
        $this->service->save($request->toDto());

        return redirect()->route('install.step3');
    }
}
