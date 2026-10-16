@extends('admin.layout')

@section('title', __('admin.page_titles.seo_audit'))

@section('content')
<div class="space-y-6 font-mono text-xs">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
                <span>&gt;</span> {{ __('admin.seo_audit.title') }}
            </h1>
            <p class="text-gray-400 mt-1">
                {{ __('admin.seo.editorial_heuristic') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ url('/llms.txt') }}" target="_blank" class="px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-[#00FF99] rounded-lg">
                Ver /llms.txt &nearr;
            </a>
            <a href="{{ url('/sitemap.xml') }}" target="_blank" class="px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 rounded-lg">
                Ver sitemap.xml &nearr;
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($counts as $count)
        <div class="cyber-card p-4 space-y-1">
            <div class="text-[10px] text-gray-400 uppercase font-bold">{{ $count['label'] }}</div>
            <div class="text-2xl font-bold text-white">{{ $count['count'] }}</div>
        </div>
        @endforeach

        <div class="cyber-card p-4 space-y-1">
            <div class="text-[10px] text-gray-400 uppercase font-bold">{{ __('admin.seo_audit.active_markets') }}</div>
            <div class="text-2xl font-bold text-[#00FF99]">{{ $markets->count() }}</div>
            <div class="text-[10px] text-gray-400">{{ __('admin.seo_audit.hreflang') }}</div>
        </div>

        <div class="cyber-card p-4 space-y-1">
            <div class="text-[10px] text-gray-400 uppercase font-bold">{{ __('admin.seo_audit.ai_geo') }}</div>
            <div class="text-base font-bold text-[#00FF99]">{{ $geoRoutesAvailable ? __('admin.seo_audit.routes_available') : __('admin.seo_audit.routes_unavailable') }}</div>
            <div class="text-[10px] text-gray-400">{{ __('admin.seo_audit.not_measured') }}</div>
        </div>
    </div>

    <!-- Audited Content Datatable -->
    <div class="cyber-card overflow-hidden">
        <div class="p-4 border-b border-gray-800 bg-[#121212] flex items-center justify-between">
            <h3 class="font-bold text-white uppercase text-xs">&gt; {{ __('admin.seo_audit.citability_title') }}</h3>
            <span class="text-[10px] text-gray-500">{{ __('admin.seo_audit.last_posts') }}</span>
        </div>

        <x-admin.table :empty="empty($auditedEntities)" emptyTitle="No hay publicaciones para auditar" emptyDescription="Publica contenido para ver su evaluación GEO en tiempo real.">
            <x-slot:header>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.seo_audit.col_content') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.seo_audit.col_type') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.seo_audit.col_words') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.seo_audit.col_score') }}</th>
                <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.seo_audit.col_recommendations') }}</th>
            </x-slot:header>

            @foreach($auditedEntities as $post)
                <tr class="hover:bg-gray-800/30 transition-colors">
                    <td class="py-3.5 px-4 font-bold text-white max-w-xs truncate">
                        <div>{{ $post['title'] }}</div>
                        <div class="text-[10px] text-gray-500">/{{ $post['slug'] }}</div>
                    </td>
                    <td class="py-3.5 px-4 text-gray-400">{{ $post['type'] }}</td>
                    <td class="py-3.5 px-4 text-gray-300">
                        <x-admin.chip>{{ $post['word_count'] }} palabras</x-admin.chip>
                    </td>
                    <td class="py-3.5 px-4">
                        <x-admin.chip variant="{{ $post['score'] >= 80 ? 'success' : ($post['score'] >= 50 ? 'warning' : 'danger') }}" size="md">
                            {{ $post['score'] }}/100
                        </x-admin.chip>
                    </td>
                    <td class="py-3.5 px-4 text-gray-400">
                        @if(empty($post['recommendations']))
                            <span class="text-[#00FF99]"><i class="fa-solid fa-check mr-1"></i> {{ __('admin.seo_audit.optimal_content') }}</span>
                        @else
                            <ul class="list-disc pl-4 space-y-0.5 text-[11px] text-amber-300">
                                @foreach(array_slice($post['recommendations'], 0, 2) as $rec)
                                    <li>{{ $rec }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>

    <!-- Schema.org JSON-LD Global Graph Preview -->
    <div class="cyber-card p-6 space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-gray-800">
            <h3 class="font-bold text-white uppercase text-xs">&gt; {{ __('admin.seo_audit.schema_graph') }}</h3>
            <span class="text-[10px] text-[#00FF99]">{{ __('admin.seo_audit.validated') }}</span>
        </div>
        <pre class="bg-[#0D0D0D] border border-gray-800 rounded-lg p-4 text-[11px] text-[#00FF99] overflow-x-auto max-h-60 leading-relaxed font-mono"><code>{{ $siteSchema }}</code></pre>
    </div>

</div>
@endsection
