@props(['cancelRoute' => null, 'submitLabel' => 'Guardar', 'submitDisabled' => false])

<div {{ $attributes->class(['flex', 'flex-wrap', 'items-center', 'justify-end', 'gap-3', 'pt-4']) }}>
    @if($cancelRoute)<x-admin.button :href="$cancelRoute" variant="neutral">Cancelar</x-admin.button>@endif
    {{ $slot }}
    <x-admin.button type="submit" variant="primary" :disabled="$submitDisabled">{{ $submitLabel }}</x-admin.button>
</div>
