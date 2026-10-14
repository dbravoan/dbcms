<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Contracts\Services\UpdatePreparationServiceInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class UpdateProposalGetController
{
    public function __invoke(Request $request, string $run, UpdatePreparationServiceInterface $service): BinaryFileResponse
    {
        return response()->download($service->proposal($run, (int) $request->user()?->getAuthIdentifier()), 'dbcms-update-review.zip')->setPrivate();
    }
}
