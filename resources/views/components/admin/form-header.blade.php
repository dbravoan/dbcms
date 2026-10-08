@props([
    'title' => '',
    'description' => '',
    'backRoute' => null,
    'backLabel' => null,
    'liveUrl' => null,
    'liveLabel' => null,
])

@php
    $backLabel = $backLabel ?? __('admin.actions.back_to_list');
    $liveLabel = $liveLabel ?? __('admin.actions.view').' &nearr;';
@endphp

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-800 font-mono text-xs">
    <div>
        <h1 class="text-xl font-bold text-white flex items-center gap-2">
            <span class="text-[#00FF99]">&gt;</span>
            <span>{{ $title }}</span>
        </h1>
        @if(!empty($description))
            <p class="text-gray-400 mt-1">{{ $description }}</p>
        @endif
    </div>

    <div class="flex items-center gap-4">
        @stack('entity_form_header')

        @if(!empty($liveUrl))
            <a href="{{ $liveUrl }}" target="_blank" class="text-[#00FF99] hover:underline font-bold flex items-center gap-1">
                <span>{!! $liveLabel !!}</span>
            </a>
        @endif

        @if(!empty($backRoute))
            <a href="{{ $backRoute }}" class="text-gray-400 hover:text-white flex items-center gap-1 transition-colors">
                <span>&larr;</span>
                <span>{{ $backLabel }}</span>
            </a>
        @endif
    </div>
</div>
