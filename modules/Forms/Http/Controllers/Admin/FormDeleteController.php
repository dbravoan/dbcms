<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Forms\Contracts\FormAdministrationServiceInterface;
use Modules\Forms\Models\CustomForm;

final class FormDeleteController extends Controller
{
    public function __construct(private readonly FormAdministrationServiceInterface $service) {}

    public function __invoke(CustomForm $form): RedirectResponse
    {
        $this->service->delete($form);

        return redirect()->route('admin.forms.index')->with('success', 'Formulario y sus envíos eliminados.');
    }
}
