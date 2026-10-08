@props([
    'column' => '',
    'label' => '',
    'align' => 'left',
    'class' => '',
])

@php
    $currentSort = request('sort_by');
    $currentDir = request('sort_dir', 'desc');
    $isActive = $currentSort === $column;
    $nextDir = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery([
        'sort_by' => $column,
        'sort_dir' => $nextDir,
        'page' => 1
    ]);
@endphp

<th {{ $attributes->merge(['class' => "py-3 px-4 text-{$align} font-bold uppercase tracking-wider text-gray-400 {$class}"]) }}>
    <a href="{{ $url }}" class="inline-flex items-center gap-1.5 hover:text-white transition-colors group">
        <span>{{ $label }}</span>
        <span class="text-[10px] {{ $isActive ? 'text-[#00FF99]' : 'text-gray-600 group-hover:text-gray-400' }}">
            @if($isActive)
                {{ $currentDir === 'asc' ? '▲' : '▼' }}
            @else
                ⇅
            @endif
        </span>
    </a>
</th>
