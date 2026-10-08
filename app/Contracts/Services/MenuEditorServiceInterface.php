<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Admin\DatatableQueryData;
use App\DTOs\Admin\MenuEditorData;

interface MenuEditorServiceInterface
{
    public function index(DatatableQueryData $input): MenuEditorData;
}
