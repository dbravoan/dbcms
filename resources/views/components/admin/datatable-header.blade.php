@props([
    'title' => '',
    'description' => '',
    'searchPlaceholder' => null,
    'createRoute' => null,
    'createLabel' => null,
    'createModalEvent' => null,
])

@php
    $searchPlaceholder = $searchPlaceholder ?? __('admin.table.search_placeholder');
    $createLabel = $createLabel ?? '+ '.__('admin.actions.create');
@endphp

<div class="space-y-4 font-mono text-xs">
    <!-- Top Row: Title & Action Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
                <span class="prompt-prefix text-[#00FF99]">></span> {{ $title }}
            </h1>
            @if(!empty($description))
                <p class="text-gray-400 mt-1">{{ $description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @isset($actions)
                {{ $actions }}
            @endisset

            @if(!empty($createRoute))
                <a href="{{ $createRoute }}" class="btn-neon">
                    {{ $createLabel }}
                </a>
            @elseif(!empty($createModalEvent))
                <button type="button" @click="$dispatch('{{ $createModalEvent }}')" class="btn-neon">
                    {{ $createLabel }}
                </button>
            @endif
        </div>
    </div>

    <!-- Toolbar: Search, Filters & Per-Page Controls -->
    <form method="GET" class="cyber-card p-4 flex flex-wrap items-center justify-between gap-4">
        <!-- Preserve existing sort params -->
        @if(request('sort_by'))
            <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
        @endif
        @if(request('sort_dir'))
            <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
        @endif

        <div class="flex flex-wrap items-center gap-3 flex-grow">
            <!-- Live Text Search Input -->
            <div class="relative min-w-0 w-full sm:w-auto sm:min-w-[240px] max-w-sm flex-grow">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="{{ $searchPlaceholder }}"
                       class="admin-control admin-control--leading-icon admin-control--trailing-icon" aria-label="{{ $searchPlaceholder }}">
                @if(request('search'))
                    <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => 1]) }}"
                       class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-500 hover:text-white">&times;</a>
                @endif
            </div>

            <!-- Extra Custom Filter Dropdowns Slot -->
            @isset($slot)
                {{ $slot }}
            @endisset

            <x-admin.button type="submit" variant="neutral" icon="fa-solid fa-filter">
                {{ __('admin.actions.filter') }}
            </x-admin.button>

            @if(request()->hasAny(['search', 'status', 'role', 'category_id', 'market_id', 'is_active', 'is_published']))
                <a href="{{ url()->current() }}" class="text-gray-500 hover:text-red-400 text-xs underline">
                    {{ __('admin.actions.clear') }}
                </a>
            @endif
        </div>

        <!-- Per Page Limit Selector -->
        <div class="flex items-center gap-2 text-gray-400">
            <span class="text-[11px] uppercase">{{ __('admin.table.show') }}</span>
            <select name="per_page" data-auto-submit class="admin-control" aria-label="{{ __('admin.table.show') }}">
                @foreach(config('cms.options.per_page') as $perPage)
                    <option value="{{ $perPage }}" {{ (string) request('per_page', '15') === (string) $perPage ? 'selected' : '' }}>{{ $perPage }}</option>
                @endforeach
            </select>
        </div>
    </form>
</div>
