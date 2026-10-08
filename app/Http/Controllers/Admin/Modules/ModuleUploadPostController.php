<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Modules;

use App\Contracts\Services\ModulePackageUploadServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModulePackageUploadRequest;
use Illuminate\Http\RedirectResponse;

final class ModuleUploadPostController extends Controller
{
    public function __construct(
        private readonly ModulePackageUploadServiceInterface $moduleManager
    ) {}

    public function __invoke(ModulePackageUploadRequest $request): RedirectResponse
    {
        return back()->with('success', $this->moduleManager->upload($request->toDto()));
    }
}
