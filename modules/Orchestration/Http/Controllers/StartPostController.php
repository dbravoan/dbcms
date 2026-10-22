<?php

declare(strict_types=1);

namespace Modules\Orchestration\Http\Controllers;

use App\Http\Adapters\Orchestration\ExecutionTransport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StartPostController extends Controller
{
    public function __construct(private readonly ExecutionTransport $transport) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->transport->start($request));
    }
}
