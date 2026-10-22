<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\BlockPackages;

use App\Core\Blocks\BlockPackageManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockPackageUploadRequest;
use Illuminate\Http\RedirectResponse;

final class BlockPackageUploadPostController extends Controller
{
    public function __construct(private readonly BlockPackageManager $packages) {}

    public function __invoke(BlockPackageUploadRequest $request): RedirectResponse
    {
        $file = $request->package();
        $this->packages->install($file->getPathname(), (int) $file->getSize());

        return redirect()->route('admin.block-packages.index')->with('success', 'Paquete instalado y desactivado.');
    }
}
