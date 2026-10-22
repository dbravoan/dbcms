<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Seo\SeoAuditReportDTO;

interface SeoAuditReportServiceInterface
{
    public function report(): SeoAuditReportDTO;
}
