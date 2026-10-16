<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Forms\Contracts\FormEditorServiceInterface;
use Modules\Forms\Models\CustomForm;

final class FormEditGetController extends Controller
{
    public function __construct(private readonly FormEditorServiceInterface $editor) {}

    public function __invoke(CustomForm $form): View
    {
        return view('forms::admin.edit', $this->editor->editing($form)->viewData());
    }
}
