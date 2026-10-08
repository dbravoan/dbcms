<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Services\Storage\ExternalStorageService;
use Illuminate\Http\RedirectResponse;

final class ConnectionsTestPostController extends Controller
{
    public function __construct(
        private readonly ExternalStorageService $storage
    ) {}

    public function __invoke(): RedirectResponse
    {
        $result = $this->storage->testConnection();

        return redirect()->route('admin.connections.index')
            ->with('storage_test_result', $result)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
