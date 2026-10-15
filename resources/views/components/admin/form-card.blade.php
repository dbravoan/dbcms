@props(['title' => null, 'subtitle' => null, 'icon' => null])

<section {{ $attributes->class(['cyber-card', 'p-6', 'space-y-5']) }}>
    @if($title)
        <header class="space-y-1">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                @if($icon)<i class="{{ $icon }}" aria-hidden="true"></i>@endif
                {{ $title }}
            </h2>
            @if($subtitle)<p class="text-sm text-gray-400">{{ $subtitle }}</p>@endif
        </header>
    @endif
    {{ $slot }}
</section>
