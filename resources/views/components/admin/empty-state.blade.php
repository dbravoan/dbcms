@props([
    'icon' => 'fa-solid fa-folder-open',
    'title' => null,
    'description' => null,
    'actionRoute' => null,
    'actionLabel' => null,
    'actionEvent' => null,
])

@php
    $title = $title ?? __('admin.table.empty_title');
    $description = $description ?? __('admin.table.empty_description');
@endphp

<div class="py-12 px-4 text-center font-mono text-xs flex flex-col items-center justify-center space-y-3">
    <div class="w-14 h-14 rounded-2xl bg-gray-900/80 border border-gray-800 text-gray-500 shadow-inner flex items-center justify-center text-2xl">
        <i class="{{ $icon }}"></i>
    </div>
    <div class="space-y-1 max-w-sm">
        <h4 class="text-sm font-bold text-white tracking-wide"><span class="prompt-prefix text-[#00FF99]">> </span>{{ $title }}</h4>
        @if($description)
            <p class="text-xs text-gray-400 leading-relaxed">{{ $description }}</p>
        @endif
    </div>

    @if($actionRoute && $actionLabel)
        <div class="pt-2">
            <a href="{{ $actionRoute }}" class="btn-neon inline-flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i> {{ $actionLabel }}
            </a>
        </div>
    @elseif($actionEvent && $actionLabel)
        <div class="pt-2">
            <button type="button" @click="$dispatch('{{ $actionEvent }}')" class="btn-neon inline-flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i> {{ $actionLabel }}
            </button>
        </div>
    @endif

    @isset($slot)
        @if(!empty(trim($slot)))
            <div class="pt-2">
                {{ $slot }}
            </div>
        @endif
    @endisset
</div>
