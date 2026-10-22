<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orchestration;

use App\Http\Adapters\Orchestration\ExecutionTransport;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ReviewGetController extends Controller
{
    public function __construct(private readonly ExecutionTransport $transport) {}

    public function __invoke(Request $request, string $execution): View
    {
        $this->transport->show($request, $execution);

        return view('admin.orchestration.review', ['executionId' => $execution]);
    }
}
