@props([
    'href' => null,
    'icon' => 'fa-solid fa-circle',
    'tooltip' => '',
    'variant' => 'default',
    'size' => 'md',
])

@php
    $dimClass = match ($size) {
        'sm' => 'w-7 h-7 text-[11px]',
        default => 'w-8 h-8 text-xs',
    };

    $colorClass = match ($variant) {
        'primary' => 'admin-action-btn-primary text-[#00FF99] bg-[#00FF99]/10 hover:bg-[#00FF99]/25 border border-[#00FF99]/30 hover:border-[#00FF99] shadow-[0_0_10px_rgba(0,255,153,0.15)]',
        'view' => 'admin-action-btn-view text-sky-400 bg-sky-500/10 hover:bg-sky-500/25 border border-sky-500/30 hover:border-sky-400',
        'danger' => 'admin-action-btn-danger text-rose-400 hover:text-white bg-rose-950/30 hover:bg-rose-900/70 border border-rose-800/40 hover:border-rose-500',
        'warning' => 'admin-action-btn-warning text-amber-400 bg-amber-500/10 hover:bg-amber-500/25 border border-amber-500/30 hover:border-amber-400',
        default => 'admin-action-btn-default text-gray-400 hover:text-white bg-gray-800/60 hover:bg-gray-700 border border-gray-700',
    };

    $baseClass = "admin-action-btn group relative inline-flex items-center justify-center rounded-lg transition-all duration-150 cursor-pointer {$dimClass} {$colorClass}";
@endphp

@if($href)
    <a href="{{ $href }}"
       title="{{ $tooltip }}"
       {{ $attributes->merge(['class' => $baseClass]) }}>
        <i class="{{ $icon }}"></i>

        @if($tooltip)
            <span class="admin-tooltip pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:flex flex-col items-center z-30 transition-opacity">
                <span class="admin-tooltip-text px-2 py-0.5 text-[10px] font-bold rounded shadow-xl whitespace-nowrap bg-[#181818] border border-gray-700 text-gray-200 font-mono">
                    {{ $tooltip }}
                </span>
                <span class="admin-tooltip-arrow w-1.5 h-1.5 rotate-45 -mt-0.5 bg-[#181818] border-r border-b border-gray-700"></span>
            </span>
        @endif
    </a>
@else
    <button type="button"
            title="{{ $tooltip }}"
            {{ $attributes->merge(['class' => $baseClass]) }}>
        <i class="{{ $icon }}"></i>

        @if($tooltip)
            <span class="admin-tooltip pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:flex flex-col items-center z-30 transition-opacity">
                <span class="admin-tooltip-text px-2 py-0.5 text-[10px] font-bold rounded shadow-xl whitespace-nowrap bg-[#181818] border border-gray-700 text-gray-200 font-mono">
                    {{ $tooltip }}
                </span>
                <span class="admin-tooltip-arrow w-1.5 h-1.5 rotate-45 -mt-0.5 bg-[#181818] border-r border-b border-gray-700"></span>
            </span>
        @endif
    </button>
@endif
