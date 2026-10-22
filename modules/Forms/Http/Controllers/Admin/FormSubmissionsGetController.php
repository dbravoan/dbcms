<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use App\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Models\FormSubmission;

final class FormSubmissionsGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = FormSubmission::with('form');

        $submissions = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
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
