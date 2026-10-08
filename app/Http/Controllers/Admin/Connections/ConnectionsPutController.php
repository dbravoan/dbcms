<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExternalStorageUpdateRequest;
use App\Services\Storage\ExternalStorageService;
use Illuminate\Http\RedirectResponse;

final class ConnectionsPutController extends Controller
{
    public function __construct(
        private readonly ExternalStorageService $storage
    ) {}

    public function __invoke(ExternalStorageUpdateRequest $request): RedirectResponse
    {
        $this->storage->save($request->validated());

        return redirect()->route('admin.connections.index')
            ->with('success', 'Conexión de almacenamiento actualizada correctamente.');
    }
}
