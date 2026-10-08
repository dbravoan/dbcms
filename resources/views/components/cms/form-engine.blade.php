@props([
    'slug' => 'contacto',
    'form' => null,
    'title' => null,
    'buttonText' => null,
    'actionUrl' => null,
    'showCard' => true,
])

@php
    $market = app()->bound('current.market') ? app('current.market') : null;
    $locale = app()->getLocale();

    $targetForm = $form;
    if (! $targetForm && $slug !== '') {
        try {
            $targetForm = app(\Modules\Forms\Services\FormDomainService::class)->getFormBySlug((string) $slug, $market);
        } catch (\Throwable) {
            // Forms module unavailable (or tables missing): fall back to the inline schema.
            $targetForm = null;
        }
    }

    $targetSlug = $targetForm?->slug ?? $slug;

    $url = $actionUrl;
    if (!$url) {
        if (app()->bound(\App\Contracts\Services\LocalizationServiceInterface::class)) {
            $url = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/forms/{$targetSlug}/submit", $market, $locale);
        } else {
            $url = url("/forms/{$targetSlug}/submit");
        }
    }

    $formTitle = $title ?? ($targetForm?->name ?? 'Contacto');
    $btnLabel = $buttonText ?? ($targetForm?->submit_button_text ?? 'Enviar Mensaje');
    $fields = $targetForm?->fields ?? collect();
    $wrapperClass = $showCard ? 'cms-card p-6 sm:p-8 rounded-2xl max-w-2xl mx-auto' : 'w-full';

    // Same renderer for database-backed schemas and for the inline array used as
    // the default contact form when no form definition exists yet.
    $schemaFactory = app(\App\Core\Forms\SchemaFactory::class);
    $schema = $fields->isNotEmpty()
        ? $schemaFactory->fromModels($fields)
        : $schemaFactory->fromArray([
            ['field_key' => 'nombre', 'type' => 'text', 'label' => 'Nombre Completo', 'placeholder' => 'Tu nombre...', 'is_required' => true],
            ['field_key' => 'email', 'type' => 'email', 'label' => 'Correo Electrónico', 'placeholder' => 'tu@email.com', 'is_required' => true],
            ['field_key' => 'mensaje', 'type' => 'textarea', 'label' => 'Mensaje o Consulta', 'placeholder' => 'Describe tu requerimiento...', 'is_required' => true],
        ]);
@endphp

<div class="{{ $wrapperClass }} font-mono text-xs">
    @if(!empty($formTitle))
        <div class="mb-6 pb-3 border-b cms-border">
            <h3 class="text-lg font-bold cms-text flex items-center gap-2">
                <span class="cms-accent">&gt;</span> {{ $formTitle }}
            </h3>
            @if($targetForm?->description)
                <p class="text-xs cms-text-muted mt-1">{{ $targetForm->description }}</p>
            @endif
        </div>
    @endif

    @if(session('form_success') || session('success'))
        <div class="p-4 rounded-xl cms-accent-soft border cms-border-accent cms-accent mb-6 flex items-center gap-3">
            <span class="text-lg"><i class="fa-solid fa-circle-check"></i></span>
            <div>{{ session('form_success') ?? session('success') }}</div>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-400 mb-6 space-y-1">
            <div class="font-bold">Por favor corrige los siguientes errores:</div>
            <ul class="list-disc pl-5 text-[11px]">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $url }}" class="space-y-4">
        @csrf

        <x-cms-form.fields :schema="$schema" variant="cyber" layout="grid" honeypot="_hp_check" />

        <div class="pt-2">
            <button type="submit" class="cms-btn cms-btn-primary w-full py-3">
                {{ $btnLabel }} &rarr;
            </button>
        </div>
    </form>
</div>
