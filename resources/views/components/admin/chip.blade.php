@props([
    'variant' => 'default', // default|neutral, market|code, brand, success|positive, warning, danger, purple|core, info|sky
    'size' => 'sm',        // sm, md
    'icon' => null,
])

@php
    $variantKey = match(strtolower((string) $variant)) {
        'market', 'code' => 'market',
        'brand' => 'brand',
        'success', 'positive', 'published', 'active' => 'success',
        'warning', 'pending' => 'warning',
        'danger', 'error', 'revoked', 'failed', 'out_of_stock' => 'danger',
        'purple', 'core' => 'purple',
        'info', 'sky', 'blue' => 'info',
        default => 'default',
    };

    $colorClass = match($variantKey) {
        'market' => 'admin-chip-market bg-black/60 text-[#00FF99] border-gray-800 font-bold',
        'brand' => 'admin-chip-brand bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/30 font-bold',
        'success' => 'admin-chip-success bg-emerald-500/10 text-emerald-400 border-emerald-500/30 font-bold',
        'warning' => 'admin-chip-warning bg-amber-500/10 text-amber-400 border-amber-500/30 font-bold',
        'danger' => 'admin-chip-danger bg-red-500/10 text-red-400 border-red-500/30 font-bold',
        'purple' => 'admin-chip-purple bg-purple-500/15 text-purple-300 border-purple-500/40 font-bold',
        'info' => 'admin-chip-info bg-sky-500/10 text-sky-400 border-sky-500/30 font-bold',
        default => 'admin-chip-default bg-gray-800 text-gray-300 border border-gray-700 font-medium',
    };
    $sizeClass = $size === 'md' ? 'px-2.5 py-1 text-xs' : 'px-1.5 py-0.5 text-[10px]';
    $baseClass = "admin-chip inline-flex items-center gap-1.5 rounded border font-mono leading-none {$sizeClass} {$colorClass}";
@endphp

<span {{ $attributes->merge(['class' => $baseClass]) }}>
    @if($icon)
        <i class="{{ $icon }} text-[10px]"></i>
    @endif
    {{ $slot }}
</span>
