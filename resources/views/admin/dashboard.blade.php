@extends('admin.layout')

@section('title', __('admin.page_titles.dashboard'))

@section('content')
<div class="space-y-8 sm:space-y-10 pb-12">
    <!-- Header Hero Banner -->
    <div class="cyber-card p-6 sm:p-7 relative overflow-hidden">
        <div class="absolute -right-20 -top-20 w-72 h-72 bg-[#00FF99]/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#00FF99] animate-pulse"></span>
                        SISTEMA OPERATIVO &bull; v{{ config('cms.version', '1.0.0') }}
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight flex items-center gap-2.5">
                    <span class="text-[#00FF99] font-mono select-none">&gt;</span> {{ __('admin.dashboard.control_panel') }} <span class="text-[#00FF99]">{{ $cmsBrandName }}</span>
                </h1>
                <p class="text-xs text-gray-400 font-mono mt-1.5">
                    {{ __('admin.dashboard.active_session_as') }} <span class="text-white font-bold">{{ auth()->user()?->name }}</span>
                    ({{ __('admin.dashboard.role') }}: <span class="text-[#00FF99] font-bold uppercase">{{ auth()->user()?->roles->pluck('name')->join(', ') ?: __('admin.dashboard.no_role') }}</span>)
                </p>
            </div>

            <!-- Header Action Buttons -->
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.ai.download-prompt') }}"
                   class="px-4 py-2.5 bg-[#1A1A1A] hover:bg-gray-800 border border-[#00FF99]/40 text-[#00FF99] font-mono font-bold rounded-lg text-xs transition-all flex items-center gap-2 shadow-sm hover:shadow-[0_0_15px_rgba(0,255,153,0.15)]"
                   title="{{ __('admin.dashboard.ai_prompt_tooltip') }}">
                    <i class="fa-solid fa-robot"></i>
                    <span>{{ __('admin.dashboard.ai_prompt') }}</span>
                    <span class="text-gray-400">&darr;</span>
                </a>
                <button type="button"
                        data-dispatch="start-onboarding-tour"
                        class="btn-neon text-xs py-2.5 px-4 shadow-sm"
                        title="{{ __('admin.dashboard.system_tour_tooltip') }}">
                    <span>&gt;_help</span>
                    <span>{{ __('admin.dashboard.system_tour') }}</span>
                </button>
                @unless(app()->isProduction())
                <a href="http://localhost:8088"
                   target="_blank"
                   class="px-4 py-2.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 hover:border-gray-500 text-gray-300 hover:text-[#00FF99] font-mono font-bold rounded-lg text-xs transition-all flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-database"></i>
                    <span>{{ __('admin.layout.adminer') }}</span>
                    <span class="text-gray-500">&nearr;</span>
                </a>
                @endunless
            </div>
        </div>
    </div>

    <!-- KPI Statistics Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-mono font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                <span class="text-[#00FF99]">&gt;</span> {{ __('admin.dashboard.metrics') }}
            </h2>
            <span class="text-[11px] font-mono text-gray-500">{{ __('admin.dashboard.realtime') }}</span>
        </div>
        <div class="flex gap-4 sm:gap-6 overflow-x-auto py-2 -my-2">
            <!-- Páginas -->
            @if(!empty($hasPages) && Route::has('admin.pages.index'))
            <a href="{{ route('admin.pages.index') }}" class="cyber-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[#00FF99]/40 hover:shadow-[0_0_20px_rgba(0,255,153,0.12)] transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-gray-400 mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-gray-300">{{ __('admin.dashboard.pages') }}</span>
                        <span class="p-1.5 rounded bg-[#1A1A1A] border border-gray-800 flex items-center justify-center text-[#00FF99]"><i class="fa-solid fa-file-lines text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-white font-mono group-hover:text-[#00FF99] transition-colors">{{ $stats['total_pages'] }}</div>
                </div>
                <div class="text-[10px] text-[#00FF99] font-mono mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.pages_hint') }}</span>
                    <span class="text-gray-500 group-hover:text-[#00FF99]">&rarr;</span>
                </div>
            </a>
            @endif

            <!-- Artículos -->
            @if(!empty($hasBlog) && Route::has('admin.blog.posts.index'))
            <a href="{{ route('admin.blog.posts.index') }}" class="cyber-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[#00FF99]/40 hover:shadow-[0_0_20px_rgba(0,255,153,0.12)] transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-gray-400 mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-gray-300">{{ __('admin.dashboard.posts') }}</span>
                        <span class="p-1.5 rounded bg-[#1A1A1A] border border-gray-800 flex items-center justify-center text-[#00FF99]"><i class="fa-solid fa-pen-to-square text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-white font-mono group-hover:text-[#00FF99] transition-colors">{{ $stats['total_posts'] }}</div>
                </div>
                <div class="text-[10px] text-[#00FF99] font-mono mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.posts_hint') }}</span>
                    <span class="text-gray-500 group-hover:text-[#00FF99]">&rarr;</span>
                </div>
            </a>
            @endif

            <!-- Productos -->
            @if(!empty($hasShop) && Route::has('admin.shop.products.index'))
            <a href="{{ route('admin.shop.products.index') }}" class="cyber-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[#00FF99]/40 hover:shadow-[0_0_20px_rgba(0,255,153,0.12)] transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-gray-400 mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-gray-300">{{ __('admin.dashboard.products') }}</span>
                        <span class="p-1.5 rounded bg-[#1A1A1A] border border-gray-800 flex items-center justify-center text-[#00FF99]"><i class="fa-solid fa-bag-shopping text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-white font-mono group-hover:text-[#00FF99] transition-colors">{{ $stats['total_products'] }}</div>
                </div>
                <div class="text-[10px] text-[#00FF99] font-mono mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.products_hint') }}</span>
                    <span class="text-gray-500 group-hover:text-[#00FF99]">&rarr;</span>
                </div>
            </a>
            @endif

            <!-- Leads Web -->
            @if(!empty($hasForms) && Route::has('admin.forms.submissions'))
            <a href="{{ route('admin.forms.submissions') }}" class="cyber-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[#00FF99]/40 hover:shadow-[0_0_20px_rgba(0,255,153,0.12)] transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-gray-400 mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-gray-300">{{ __('admin.dashboard.leads') }}</span>
                        <span class="p-1.5 rounded bg-[#1A1A1A] border border-gray-800 flex items-center justify-center text-[#00FF99]"><i class="fa-solid fa-inbox text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-[#00FF99] font-mono">{{ $stats['total_submissions'] }}</div>
                </div>
                <div class="text-[10px] text-[#00FF99] font-mono mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.leads_hint') }}</span>
                    <span class="text-gray-500 group-hover:text-[#00FF99]">&rarr;</span>
                </div>
            </a>
            @endif

            <!-- Módulos -->
            <a href="{{ route('admin.modules.index') }}" class="cyber-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[#00FF99]/40 hover:shadow-[0_0_20px_rgba(0,255,153,0.12)] transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-gray-400 mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-gray-300">{{ __('admin.dashboard.modules') }}</span>
                        <span class="p-1.5 rounded bg-[#1A1A1A] border border-gray-800 flex items-center justify-center text-[#00FF99]"><i class="fa-solid fa-puzzle-piece text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-white font-mono group-hover:text-[#00FF99] transition-colors">{{ $stats['installed_modules'] }}</div>
                </div>
                <div class="text-[10px] text-[#00FF99] font-mono mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.modules_hint') }}</span>
                    <span class="text-gray-500 group-hover:text-[#00FF99]">&rarr;</span>
                </div>
            </a>

            <!-- Mercados -->
            <a href="{{ route('admin.markets.index') }}" class="cyber-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[#00FF99]/40 hover:shadow-[0_0_20px_rgba(0,255,153,0.12)] transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-gray-400 mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-gray-300">{{ __('admin.dashboard.markets') }}</span>
                        <span class="p-1.5 rounded bg-[#1A1A1A] border border-gray-800 flex items-center justify-center text-[#00FF99]"><i class="fa-solid fa-globe text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-white font-mono group-hover:text-[#00FF99] transition-colors">{{ $stats['active_markets'] }}</div>
                </div>
                <div class="text-[10px] text-[#00FF99] font-mono mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.markets_hint') }}</span>
                    <span class="text-gray-500 group-hover:text-[#00FF99]">&rarr;</span>
                </div>
            </a>

            <!-- Usuarios -->
            <a href="{{ route('admin.users.index') }}" class="cyber-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[#00FF99]/40 hover:shadow-[0_0_20px_rgba(0,255,153,0.12)] transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-gray-400 mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-gray-300">{{ __('admin.dashboard.users') }}</span>
                        <span class="p-1.5 rounded bg-[#1A1A1A] border border-gray-800 flex items-center justify-center text-[#00FF99]"><i class="fa-solid fa-users text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-white font-mono group-hover:text-[#00FF99] transition-colors">{{ $stats['total_users'] }}</div>
                </div>
                <div class="text-[10px] text-[#00FF99] font-mono mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.users_hint') }}</span>
                    <span class="text-gray-500 group-hover:text-[#00FF99]">&rarr;</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Quick Creation & Action Hub -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-mono font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                <span class="text-[#00FF99]">&gt;</span> {{ __('admin.dashboard.quick_actions') }}
            </h2>
            <span class="text-[11px] font-mono text-gray-500">{{ __('admin.dashboard.frequent_flows') }}</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6">
            @if(!empty($hasPages) && Route::has('admin.pages.create'))
            <a href="{{ route('admin.pages.create') }}" class="cyber-card p-5 hover:border-[#00FF99]/50 hover:bg-[#1A1A1A] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 group-hover:border-[#00FF99]/40 transition-colors flex items-center justify-center text-[#00FF99] text-base"><i class="fa-solid fa-file-lines"></i></span>
                    <span class="text-gray-500 group-hover:text-[#00FF99] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-white text-sm group-hover:text-[#00FF99] transition-colors">{{ __('admin.dashboard.new_page') }}</h3>
                <p class="text-xs text-gray-400 mt-1 leading-relaxed">{{ __('admin.dashboard.new_page_desc') }}</p>
            </a>
            @endif

            @if(!empty($hasBlog) && Route::has('admin.blog.posts.create'))
            <a href="{{ route('admin.blog.posts.create') }}" class="cyber-card p-5 hover:border-[#00FF99]/50 hover:bg-[#1A1A1A] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 group-hover:border-[#00FF99]/40 transition-colors flex items-center justify-center text-[#00FF99] text-base"><i class="fa-solid fa-pen-nib"></i></span>
                    <span class="text-gray-500 group-hover:text-[#00FF99] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-white text-sm group-hover:text-[#00FF99] transition-colors">{{ __('admin.dashboard.write_post') }}</h3>
                <p class="text-xs text-gray-400 mt-1 leading-relaxed">{{ __('admin.dashboard.write_post_desc') }}</p>
            </a>
            @endif

            @if(!empty($hasForms) && Route::has('admin.forms.index'))
            <a href="{{ route('admin.forms.index') }}" class="cyber-card p-5 hover:border-[#00FF99]/50 hover:bg-[#1A1A1A] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 group-hover:border-[#00FF99]/40 transition-colors flex items-center justify-center text-[#00FF99] text-base"><i class="fa-solid fa-inbox"></i></span>
                    <span class="text-gray-500 group-hover:text-[#00FF99] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-white text-sm group-hover:text-[#00FF99] transition-colors">{{ __('admin.dashboard.manage_forms') }}</h3>
                <p class="text-xs text-gray-400 mt-1 leading-relaxed">{{ __('admin.dashboard.manage_forms_desc') }}</p>
            </a>
            @endif

            @if(Route::has('admin.seo.audit'))
            <a href="{{ route('admin.seo.audit') }}" class="cyber-card p-5 hover:border-[#00FF99]/50 hover:bg-[#1A1A1A] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 group-hover:border-[#00FF99]/40 transition-colors flex items-center justify-center text-[#00FF99] text-base"><i class="fa-solid fa-bullseye"></i></span>
                    <span class="text-gray-500 group-hover:text-[#00FF99] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-white text-sm group-hover:text-[#00FF99] transition-colors">{{ __('admin.dashboard.seo_audit') }}</h3>
                <p class="text-xs text-gray-400 mt-1 leading-relaxed">{{ __('admin.dashboard.seo_audit_desc') }}</p>
            </a>
            @endif

            @if(Route::has('admin.modules.index'))
            <a href="{{ route('admin.modules.index') }}" class="cyber-card p-5 hover:border-[#00FF99]/50 hover:bg-[#1A1A1A] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 group-hover:border-[#00FF99]/40 transition-colors flex items-center justify-center text-[#00FF99] text-base"><i class="fa-solid fa-puzzle-piece"></i></span>
                    <span class="text-gray-500 group-hover:text-[#00FF99] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-white text-sm group-hover:text-[#00FF99] transition-colors">{{ __('admin.dashboard.modules_marketplace') }}</h3>
                <p class="text-xs text-gray-400 mt-1 leading-relaxed">{{ __('admin.dashboard.modules_marketplace_desc', ['app' => config('cms.name')]) }}</p>
            </a>
            @endif
        </div>
    </div>

    <!-- Two Column Detail Section -->
    @if((!empty($hasForms) && Route::has('admin.forms.submissions')) || (!empty($hasBlog) && Route::has('admin.blog.posts.index')))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
        <!-- Recent Leads Submissions -->
        @if(!empty($hasForms) && Route::has('admin.forms.submissions'))
        <div class="cyber-card p-6 sm:p-7 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-inbox text-[#00FF99] text-base"></i>
                        <h3 class="font-mono text-sm font-bold text-white flex items-center gap-2">
                            <span class="text-[#00FF99]">&gt;</span> {{ __('admin.dashboard.recent_leads') }}
                        </h3>
                    </div>
                    <a href="{{ route('admin.forms.submissions') }}" class="text-xs font-mono text-[#00FF99] hover:underline flex items-center gap-1">
                        <span>{{ __('admin.dashboard.view_all') }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                @if($recentSubmissions->isEmpty())
                    <div class="text-center py-12 text-xs font-mono text-gray-500">
                        <div class="text-2xl mb-2 opacity-50"><i class="fa-solid fa-inbox"></i></div>
                        &gt; {{ __('admin.dashboard.no_submissions') }}
                    </div>
                @else
                    <div class="divide-y divide-gray-800/80 font-mono text-xs mt-3">
                        @foreach($recentSubmissions as $sub)
                            <div class="py-3.5 flex items-center justify-between hover:bg-gray-800/30 px-2 rounded-lg transition-colors">
                                <div class="truncate max-w-[70%]">
                                    <div class="font-bold text-white flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#00FF99]"></span>
                                        <span class="truncate">{{ $sub->form->name ?? __('admin.dashboard.contact_form') }}</span>
                                    </div>
                                    <div class="text-gray-400 text-[11px] mt-0.5 pl-3.5 truncate">
                                        {{ $sub->payload['email'] ?? $sub->payload['nombre'] ?? __('admin.dashboard.anonymous_contact') }}
                                    </div>
                                </div>
                                <span class="text-[10px] text-gray-500 shrink-0 bg-[#1A1A1A] px-2 py-1 rounded border border-gray-800">
                                    {{ $sub->created_at->diffForHumans() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-4 mt-4 border-t border-gray-800/80 flex items-center justify-between text-[11px] font-mono text-gray-400">
                <span>{{ __('admin.dashboard.total_received') }} <strong class="text-[#00FF99]">{{ $stats['total_submissions'] }}</strong></span>
                <a href="{{ route('admin.forms.submissions') }}" class="hover:text-[#00FF99] transition-colors">{{ __('admin.dashboard.full_inbox') }} &nearr;</a>
            </div>
        </div>
        @endif

        <!-- Recent Blog Posts -->
        @if(!empty($hasBlog) && Route::has('admin.blog.posts.index'))
        <div class="cyber-card p-6 sm:p-7 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-pen-to-square text-[#00FF99] text-base"></i>
                        <h3 class="font-mono text-sm font-bold text-white flex items-center gap-2">
                            <span class="text-[#00FF99]">&gt;</span> {{ __('admin.dashboard.published_posts') }}
                        </h3>
                    </div>
                    <a href="{{ route('admin.blog.posts.index') }}" class="text-xs font-mono text-[#00FF99] hover:underline flex items-center gap-1">
                        <span>{{ __('admin.dashboard.view_all') }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                @if($recentPosts->isEmpty())
                    <div class="text-center py-12 text-xs font-mono text-gray-500">
                        <div class="text-2xl mb-2 opacity-50"><i class="fa-solid fa-newspaper"></i></div>
                        &gt; {{ __('admin.dashboard.no_posts') }}
                    </div>
                @else
                    <div class="divide-y divide-gray-800/80 font-mono text-xs mt-3">
                        @foreach($recentPosts as $post)
                            <div class="py-3.5 flex items-center justify-between hover:bg-gray-800/30 px-2 rounded-lg transition-colors">
                                <div class="truncate max-w-[70%]">
                                    <div class="font-bold text-white truncate hover:text-[#00FF99] transition-colors">
                                        <a href="{{ route('admin.blog.posts.edit', $post) }}">
                                            {{ $post->translations->first()?->title ?? __('admin.dashboard.untitled_post') }}
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ __('admin.dashboard.by') }} <span class="text-gray-300">{{ $post->author->name ?? 'Admin' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30">
                                        {{ $post->is_published ? __('admin.dashboard.published') : __('admin.status.draft') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-4 mt-4 border-t border-gray-800/80 flex items-center justify-between text-[11px] font-mono text-gray-400">
                <span>{{ __('admin.dashboard.total_posts') }} <strong class="text-white">{{ $stats['total_posts'] }}</strong></span>
                @if(Route::has('admin.blog.posts.create'))
                <a href="{{ route('admin.blog.posts.create') }}" class="text-[#00FF99] hover:underline font-bold">{{ __('admin.dashboard.new_post') }}</a>
                @endif
            </div>
        </div>
        @endif
    </div>
    @else
    <!-- Zero Modules Clean Install Notice -->
    <div class="cyber-card p-8 text-center border border-[#00FF99]/20 bg-[#141414]/90">
        <div class="w-16 h-16 rounded-2xl bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] flex items-center justify-center text-2xl mx-auto mb-4">
            <i class="fa-solid fa-microchip"></i>
        </div>
        <h3 class="text-lg font-bold text-white font-mono">{{ __('admin.dashboard.clean_install_title') }}</h3>
        <p class="text-xs text-gray-400 max-w-xl mx-auto mt-2 leading-relaxed font-mono">
            {{ $cmsBrandName }} {{ __('admin.dashboard.clean_install_desc') }}
        </p>
        <div class="mt-6 flex flex-wrap justify-center gap-4">
            <a href="{{ route('admin.modules.index') }}" class="btn-neon text-xs py-2.5 px-6">
                <span>&gt;_</span>
                <span>{{ __('admin.dashboard.explore_modules') }}</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white rounded-lg text-xs font-mono font-bold transition-all">
                <span>{{ __('admin.dashboard.manage_users') }}</span>
            </a>
        </div>
    </div>
    @endif

    <!-- Architecture & Diagnostics Overview -->
    <div class="cyber-card p-6 border border-gray-800/80 bg-[#121212]/90">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-gray-800/80 gap-2">
            <div class="flex items-center gap-2 text-xs font-mono font-bold text-white">
                <span class="text-[#00FF99]">&gt;</span> {{ __('admin.dashboard.architecture') }}
            </div>
            <div class="text-[11px] font-mono text-gray-400 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#00FF99] inline-block animate-pulse"></span>
                <span>{{ __('admin.dashboard.isolated_env') }}</span>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-5 text-xs font-mono">
            <div class="p-3 bg-[#171717] rounded-lg border border-gray-800/80">
                <div class="text-[10px] text-gray-500 uppercase">{{ __('admin.dashboard.framework') }}</div>
                <div class="text-white font-bold mt-1">Laravel {{ app()->version() }}</div>
                <div class="text-[10px] text-[#00FF99] mt-1">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }} &bull; Pint Clean</div>
            </div>
            <div class="p-3 bg-[#171717] rounded-lg border border-gray-800/80">
                <div class="text-[10px] text-gray-500 uppercase">{{ __('admin.dashboard.database') }}</div>
                <div class="text-white font-bold mt-1">MySQL 8.0</div>
                <div class="text-[10px] text-[#00FF99] mt-1">{{ __('admin.dashboard.testing_db') }}</div>
            </div>
            <div class="p-3 bg-[#171717] rounded-lg border border-gray-800/80">
                <div class="text-[10px] text-gray-500 uppercase">{{ __('admin.dashboard.ai_protocol') }}</div>
                <div class="text-white font-bold mt-1">{{ __('admin.dashboard.mcp_endpoint') }}</div>
                <div class="text-[10px] text-[#00FF99] mt-1">{{ __('admin.dashboard.sse_active') }}</div>
            </div>
            <div class="p-3 bg-[#171717] rounded-lg border border-gray-800/80">
                <div class="text-[10px] text-gray-500 uppercase">{{ __('admin.dashboard.multi_market') }}</div>
                <div class="text-white font-bold mt-1">{{ __('admin.dashboard.rfc') }}</div>
                <div class="text-[10px] text-[#00FF99] mt-1">{{ __('admin.dashboard.i18n_geo') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
