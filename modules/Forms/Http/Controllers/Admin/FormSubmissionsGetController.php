<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Models\FormSubmission;

final class FormSubmissionsGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = FormSubmission::with('form');

        $submissions = $this->applyDatatable(
            $query,
            $request,
            ['ip_address'],
            ['form_id' => 'custom_form_id'],
            'created_at',
            'desc'
        );

        $forms = CustomForm::all();

        return view('forms::admin.submissions', [
            'submissions' => $submissions,
            'forms' => $forms,
        ]);
    }
}
