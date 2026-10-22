<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Forms\Contracts\FormAdministrationServiceInterface;
use Modules\Forms\Http\Requests\FormMutationRequest;
use Modules\Forms\Models\CustomForm;

final class FormPutController extends Controller
{
    public function __construct(private readonly FormAdministrationServiceInterface $service) {}

    public function __invoke(FormMutationRequest $request, CustomForm $form): RedirectResponse
    {
        $this->service->update($form, $request->validated());

        return redirect()->route('admin.forms.edit', $form)->with('success', 'Formulario guardado; los cambios publicados pendientes requieren revisión.');
    }
}
