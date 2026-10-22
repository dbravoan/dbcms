<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class MigrationGetController extends Controller
{
    public function __invoke(): View
    {
        return view('install.step3_migrate');
    }
}
