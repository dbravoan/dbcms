@extends('admin.layout')

@section('title', __('admin.page_titles.themes'))

@section('content')
<div class="space-y-6 font-mono text-xs">
    <div>
        <h1 class="text-xl font-bold text-white flex items-center gap-2">
            <span>&gt;</span> {{ __('admin.themes.manager_title') }}
        </h1>
        <p class="text-gray-400 mt-1">
            {{ __('admin.themes.manager_subtitle') }} <span class="text-[#00FF99] font-bold">{{ $activeTheme }}</span>.
        </p>
    </div>

    <!-- Themes Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($themes as $identifier => $theme)
            <div class="cyber-card p-6 flex flex-col justify-between space-y-4 {{ $identifier === $activeTheme ? 'border-[#00FF99] shadow-[0_0_30px_rgba(0,255,153,0.15)]' : '' }}">
                <div>
                    <!-- Theme Header Badge -->
                    <div class="flex items-center justify-between">
                        <span class="text-base font-bold text-white">{{ $theme['name'] ?? ucfirst($identifier) }}</span>
                        @if($identifier === $activeTheme)
                            <span class="px-2 py-0.5 rounded bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99] font-bold text-[10px]">
                                {{ __('admin.themes.active') }}
                            </span>
                        @else
                            <span class="text-gray-500 text-[10px]">v{{ $theme['version'] ?? '1.0.0' }}</span>
                        @endif
                    </div>

                    <p class="text-gray-400 text-xs mt-2 leading-relaxed">
                        {{ $theme['description'] ?? __('admin.themes.default_description') }}
                    </p>

                    <div class="mt-4 p-3 bg-[#0D0D0D] rounded border border-gray-800 text-[11px] text-gray-400 space-y-1">
                        <div>{{ __('admin.themes.directory') }}: <code class="text-gray-300">themes/{{ $identifier }}/</code></div>
                        <div>{{ __('admin.themes.author') }}: <span class="text-gray-300">{{ $theme['author'] ?? '—' }}</span></div>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-800 flex items-center justify-between">
                    @if($identifier === $activeTheme)
                        <button type="button" disabled class="px-4 py-2 bg-[#00FF99]/20 text-[#00FF99] border border-[#00FF99]/40 rounded font-bold cursor-default">
                            <i class="fa-solid fa-check mr-1"></i> {{ __('admin.themes.current') }}
                        </button>
                    @else
                        <form method="POST" action="{{ route('admin.themes.activate', $identifier) }}">
                            @csrf
                            <button type="submit" class="btn-neon">
                                {{ __('admin.themes.activate') }} &rarr;
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
