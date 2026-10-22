<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\BlockPackages;

use App\Core\Blocks\BlockPackageManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class BlockPackageDeleteController extends Controller
{
    public function __construct(private readonly BlockPackageManager $packages) {}

    public function __invoke(string $identifier): RedirectResponse
    {
        $this->packages->uninstall($identifier);

        return back()->with('success', 'Paquete desinstalado. Sus instancias permanecen guardadas.');
    }
}
