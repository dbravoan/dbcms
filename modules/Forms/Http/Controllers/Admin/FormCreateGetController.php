<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Forms\Contracts\FormEditorServiceInterface;

final class FormCreateGetController extends Controller
{
    public function __construct(private readonly FormEditorServiceInterface $editor) {}

    public function __invoke(): View
    {
        return view('forms::admin.create', $this->editor->creation()->viewData());
    }
}
