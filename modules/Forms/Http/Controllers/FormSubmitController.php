<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers;

use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Services\FormDomainService;

class FormSubmitController extends Controller
{
    public function submit(Request $request, FormDomainService $service, ?string $formSlug = null): JsonResponse|RedirectResponse
    {
        $routeSlug = $request->route('formSlug');
        $slug = is_string($routeSlug) ? $routeSlug : (string) $formSlug;

        /** @var Market|null $market */
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->getLocale();

        /** @var CustomForm $form */
        $form = $service->getFormBySlug($slug, $market) ?? abort(404);

        // The captcha (if the form declares the field) is validated by the
        // domain service through the form field framework.
        $submission = $service->submitForm($form, $request->all(), $market, $locale);

        if (app()->bound(ModuleNotificationServiceInterface::class)) {
            /** @var ModuleNotificationServiceInterface $notifier */
            $notifier = app(ModuleNotificationServiceInterface::class);
            $notifier->notify(
                module: 'forms',
                type: 'lead',
                title: "Nuevo Lead: {$form->name}",
                message: "Se ha recibido un nuevo contacto a través del formulario #{$form->slug}.",
                actionUrl: route('admin.forms.submissions', ['form_id' => $form->id]),
                data: ['submission_id' => $submission->id, 'form_id' => $form->id, 'locale' => $locale],
                sendEmail: true
            );
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $form->success_message ?? 'Thank you! Your submission has been received.',
                'submission_id' => $submission->id,
            ]);
        }

        return back()
            ->with('form_success', $form->success_message ?? 'Thank you! Your submission has been received.')
            ->with('success', $form->success_message ?? 'Thank you! Your submission has been received.');
    }
}
