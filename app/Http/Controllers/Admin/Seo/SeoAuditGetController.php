<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Seo;

use App\Contracts\Services\SeoAuditReportServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SeoAuditGetController extends Controller
{
    public function __construct(
        private readonly SeoAuditReportServiceInterface $report
    ) {}

    public function __invoke(Request $request): View
    {
        return view('admin.seo.audit', $this->report->report()->toArray());
    }
}
