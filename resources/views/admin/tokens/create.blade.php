@extends('admin.layout')

@section('title', __('admin.tokens.page_title'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6 font-mono text-xs" x-data="{ mode: 'personal' }">

    <!-- Top Navigation Breadcrumb -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.tokens.index') }}" class="text-gray-400 hover:text-white flex items-center gap-2">
            <span>&larr;</span>
            <span>{{ __('admin.tokens.back') }}</span>
        </a>
    </div>

    <!-- Mode Selector Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div @click="mode = 'personal'" :class="mode === 'personal' ? 'border-[#00FF99] bg-[#141414] ring-1 ring-[#00FF99]' : 'border-gray-800 bg-[#121212] opacity-70 hover:opacity-100'" class="p-5 rounded-xl border cursor-pointer transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl text-[#00FF99]"><i class="fa-solid fa-user"></i></span>
                <div>
                    <h3 class="font-bold text-white text-sm">{{ __('admin.tokens.pat_card_title') }}</h3>
                    <p class="text-gray-400 text-[11px] mt-0.5">{{ __('admin.tokens.pat_card_desc') }}</p>
                </div>
            </div>
        </div>

        <div @click="mode = 'm2m'" :class="mode === 'm2m' ? 'border-purple-500 bg-[#141414] ring-1 ring-purple-500' : 'border-gray-800 bg-[#121212] opacity-70 hover:opacity-100'" class="p-5 rounded-xl border cursor-pointer transition-all">
            <div class="flex items-center gap-3">
                <span class="text-2xl text-purple-400"><i class="fa-solid fa-robot"></i></span>
                <div>
                    <h3 class="font-bold text-white text-sm">{{ __('admin.tokens.m2m_card_title') }}</h3>
                    <p class="text-gray-400 text-[11px] mt-0.5">{{ __('admin.tokens.m2m_card_desc') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Mode 1: Personal Access Token Form -->
    <div x-show="mode === 'personal'" class="cyber-card p-6 bg-[#141414] border border-gray-800 rounded-xl space-y-6">
        <div class="border-b border-gray-800 pb-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-[#00FF99]"></i>
                <span>{{ __('admin.tokens.pat_form_title') }}</span>
            </h2>
            <p class="text-gray-400 text-xs mt-1">{{ __('admin.tokens.pat_form_desc') }}</p>
        </div>

        <form method="POST" action="{{ route('admin.tokens.store-personal') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="subject_type" value="users" />

            <div>
                <label class="block text-gray-300 font-bold mb-2">{{ __('admin.tokens.token_name_label') }} <span class="text-red-400">*</span></label>
                <input type="text" name="name" required placeholder="{{ __('admin.tokens.token_name_placeholder') }}" class="w-full bg-[#181818] border border-gray-700 rounded-lg px-4 py-2.5 text-white text-xs focus:outline-none focus:border-[#00FF99]" />
                <p class="text-[11px] text-gray-500 mt-1">{{ __('admin.tokens.token_name_hint') }}</p>
            </div>

            <div>
                <label class="block text-gray-300 font-bold mb-2">{{ __('admin.tokens.subject_label') }} <span class="text-red-400">*</span></label>
                <select name="subject_id" required class="w-full bg-[#181818] border border-gray-700 rounded-lg px-4 py-2.5 text-white text-xs focus:outline-none focus:border-[#00FF99]">
                    <optgroup label="{{ __('admin.tokens.core_users') }}">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </optgroup>
                    @foreach($moduleProviders as $provKey => $prov)
                        @if(count($prov['records']) > 0)
                            <optgroup label="{{ __('admin.tokens.module_group', ['name' => $prov['name'], 'key' => $provKey]) }}">
                                @foreach($prov['records'] as $rec)
                                    <option value="{{ $rec->id }}">{{ $rec->name ?? 'ID '.$rec->id }} ({{ $rec->email ?? $provKey }})</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
                <p class="text-[11px] text-gray-500 mt-1">{{ __('admin.tokens.subject_hint') }}</p>
            </div>

            <!-- Scopes Selection -->
            <div>
                <label class="block text-gray-300 font-bold mb-2">{{ __('admin.tokens.scopes_label') }}</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-[#181818] p-4 rounded-xl border border-gray-800 max-h-64 overflow-y-auto">
                    @foreach($availableScopes as $scopeKey => $scopeDesc)
                        <label class="flex items-start gap-2.5 p-2 rounded hover:bg-gray-800/40 cursor-pointer">
                            <input type="checkbox" name="scopes[]" value="{{ $scopeKey }}" class="mt-0.5 rounded bg-gray-900 border-gray-700 text-[#00FF99] focus:ring-[#00FF99]" {{ str_starts_with($scopeKey, 'mcp:') ? 'checked' : '' }} />
                            <div>
                                <span class="text-[#00FF99] font-bold block">{{ $scopeKey }}</span>
                                <span class="text-gray-400 text-[10px]">{{ $scopeDesc }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-800">
                <a href="{{ route('admin.tokens.index') }}" class="px-4 py-2 rounded-lg text-gray-400 hover:text-white">{{ __('admin.actions.cancel') }}</a>
                <button type="submit" class="px-5 py-2.5 bg-[#00FF99] hover:bg-[#00cc7a] text-black font-bold rounded-lg transition-colors">
                    {{ __('admin.tokens.generate_pat') }} &rarr;
                </button>
            </div>
        </form>
    </div>

    <!-- Mode 2: M2M Client Form -->
    <div x-show="mode === 'm2m'" class="cyber-card p-6 bg-[#141414] border border-gray-800 rounded-xl space-y-6">
        <div class="border-b border-gray-800 pb-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-robot text-purple-400"></i>
                <span>{{ __('admin.tokens.m2m_form_title') }}</span>
            </h2>
            <p class="text-gray-400 text-xs mt-1">{!! __('admin.tokens.m2m_form_desc') !!}</p>
        </div>

        <form method="POST" action="{{ route('admin.tokens.store-m2m') }}" class="space-y-6">
            @csrf

            <div>
                <label class="block text-gray-300 font-bold mb-2">{{ __('admin.tokens.app_name_label') }} <span class="text-red-400">*</span></label>
                <input type="text" name="name" required placeholder="{{ __('admin.tokens.app_name_placeholder') }}" class="w-full bg-[#181818] border border-gray-700 rounded-lg px-4 py-2.5 text-white text-xs focus:outline-none focus:border-purple-500" />
            </div>

            <div>
                <label for="service-role" class="block text-gray-300 font-bold mb-2">Rol de la cuenta de servicio</label>
                <select id="service-role" name="role_id" required class="w-full bg-[#181818] border border-gray-700 rounded-lg px-4 py-2.5 text-white text-xs">
                    <option value="">Selecciona sus permisos</option>
                    @foreach($serviceRoles as $serviceRole)
                        <option value="{{ $serviceRole->getKey() }}" @selected(old('role_id') == $serviceRole->getKey() || (!old('role_id') && $serviceRole->slug === 'author'))>{{ $serviceRole->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Scopes Selection -->
            <div>
                <label class="block text-gray-300 font-bold mb-2">{{ __('admin.tokens.m2m_scopes_label') }}</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-[#181818] p-4 rounded-xl border border-gray-800 max-h-64 overflow-y-auto">
                    @foreach($availableScopes as $scopeKey => $scopeDesc)
                        <label class="flex items-start gap-2.5 p-2 rounded hover:bg-gray-800/40 cursor-pointer">
                            <input type="checkbox" name="scopes[]" value="{{ $scopeKey }}" class="mt-0.5 rounded bg-gray-900 border-gray-700 text-purple-500 focus:ring-purple-500" {{ in_array($scopeKey, ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:destructive', 'mcp:publish', 'm2m:service']) ? 'checked' : '' }} />
                            <div>
                                <span class="text-purple-400 font-bold block">{{ $scopeKey }}</span>
                                <span class="text-gray-400 text-[10px]">{{ $scopeDesc }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- M2M Explanation Box -->
            <div class="bg-purple-950/30 border border-purple-800/60 rounded-lg p-4 text-gray-300 space-y-2">
                <div class="font-bold text-purple-300">{{ __('admin.tokens.m2m_flow_title') }}</div>
                <div class="text-[11px] text-gray-400">
                    {!! __('admin.tokens.m2m_flow_desc') !!}
                </div>
                <pre class="bg-black/60 p-2.5 rounded text-purple-300 text-[10px] overflow-x-auto">grant_type=client_credentials&client_id={ID}&client_secret={SECRET}&scope=mcp:access mcp:publish</pre>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-800">
                <a href="{{ route('admin.tokens.index') }}" class="px-4 py-2 rounded-lg text-gray-400 hover:text-white">{{ __('admin.actions.cancel') }}</a>
                <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg transition-colors">
                    {{ __('admin.tokens.create_m2m') }} &rarr;
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
