<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Contracts\Services\InstallationViewServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class AdminGetController extends Controller
{
    public function __construct(private readonly InstallationViewServiceInterface $service) {}

    public function __invoke(): View
    {
        return view('install.step4_admin', $this->service->administrator());
    }
}
