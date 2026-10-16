@props([
    'slug' => 'contacto',
    'form' => null,
    'title' => null,
    'buttonText' => null,
    'actionUrl' => null,
    'showCard' => true,
])

@if($targetForm)
<div class="{{ $wrapperClass }} font-mono text-xs">
    @if(!empty($formTitle))
        <div class="mb-6 pb-3 border-b cms-border">
            <h3 class="text-lg font-bold cms-text flex items-center gap-2">
                <span class="cms-accent">&gt;</span> {{ $formTitle }}
            </h3>
            @if($formDescription)
                <p class="text-xs cms-text-muted mt-1">{{ $formDescription }}</p>
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

@endif
