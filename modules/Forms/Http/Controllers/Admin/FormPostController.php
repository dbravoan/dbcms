<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Forms\Contracts\FormAdministrationServiceInterface;
use Modules\Forms\Http\Requests\FormMutationRequest;

final class FormPostController extends Controller
{
    public function __construct(private readonly FormAdministrationServiceInterface $service) {}

    public function __invoke(FormMutationRequest $request): RedirectResponse
    {
        $form = $this->service->create($request->validated(), $request->user());

        return redirect()->route('admin.forms.edit', $form)->with('success', "Formulario '{$form->name}' creado correctamente.");
    }
}
