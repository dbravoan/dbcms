<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Forms\Models\FormSubmission;

final class FormSubmissionDeleteController extends Controller
{
    public function __invoke(FormSubmission $submission): RedirectResponse
    {
        $submission->delete();

        return redirect()->route('admin.forms.submissions')->with('success', 'Envío de lead eliminado correctamente.');
    }
}
