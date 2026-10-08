@extends('admin.layout')

@section('title', __('admin.page_titles.tokens'))

@section('content')
<div class="space-y-6 font-mono text-xs" x-data="{ activeTab: 'personal' }">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-[#141414] border border-gray-800 rounded-xl p-5">
        <div>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-key text-[#00FF99]"></i>
                <span>{{ __('admin.tokens.title') }}</span>
            </h1>
            <p class="text-gray-400 mt-1">
                Administra Personal Access Tokens para usuarios y Clientes Machine-to-Machine (M2M) para agentes autónomos y protocolos MCP.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tokens.create') }}" class="px-4 py-2 bg-[#00FF99] hover:bg-[#00cc7a] text-black font-bold rounded-lg transition-colors flex items-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>{{ __('admin.tokens.create_button') }}</span>
            </a>
        </div>
    </div>

    <!-- Alert for Newly Issued Token (Single-View Secret) -->
    @if(session('issued_token'))
    <div class="bg-black/90 border-2 border-[#00FF99] rounded-xl p-5 text-white shadow-2xl space-y-3" x-data="{ copied: false }">
        <div class="flex items-center gap-2 text-[#00FF99] font-bold text-sm">
            <i class="fa-solid fa-shield-halved"></i>
            <span>{{ __('admin.tokens.pat_success_title') }}</span>
        </div>
        <p class="text-gray-300 text-xs">
            {!! __('admin.tokens.pat_success_desc') !!}
        </p>
        <div class="flex items-center gap-2 bg-[#121212] p-3 rounded-lg border border-gray-700">
            <input type="text" readonly id="plainTokenInput" value="{{ session('issued_token')['token'] }}" class="w-full bg-transparent text-[#00FF99] text-xs font-mono select-all focus:outline-none" />
            <button type="button" @click="navigator.clipboard.writeText('{{ session('issued_token')['token'] }}'); copied = true; setTimeout(() => copied = false, 3000)" class="px-3 py-1 bg-[#1A1A1A] hover:bg-gray-800 border border-[#00FF99]/40 text-[#00FF99] rounded font-bold transition-colors">
                <span x-text="copied ? @js(__('admin.tokens.copied')) : @js(__('admin.tokens.copy'))"></span>
            </button>
        </div>
    </div>
    @endif

    <!-- Alert for Newly Issued M2M Client -->
    @if(session('issued_m2m_client'))
    <div class="bg-black/90 border-2 border-purple-500 rounded-xl p-5 text-white shadow-2xl space-y-3" x-data="{ copiedSecret: false }">
        <div class="flex items-center gap-2 text-purple-400 font-bold text-sm">
            <i class="fa-solid fa-robot"></i>
            <span>{{ __('admin.tokens.m2m_success_title') }}</span>
        </div>
        <p class="text-gray-300 text-xs">
            {!! __('admin.tokens.m2m_success_desc') !!}
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-[#121212] p-3 rounded-lg border border-gray-800">
                <span class="text-gray-500 text-[10px] uppercase block">{{ __('admin.tokens.client_id') }}</span>
                <span class="text-white font-mono text-xs select-all">{{ session('issued_m2m_client')['client_id'] }}</span>
            </div>
            <div class="bg-[#121212] p-3 rounded-lg border border-gray-800 flex items-center justify-between">
                <div>
                    <span class="text-gray-500 text-[10px] uppercase block">{{ __('admin.tokens.client_secret') }}</span>
                    <span class="text-purple-400 font-mono text-xs select-all">{{ session('issued_m2m_client')['client_secret'] }}</span>
                </div>
                <button type="button" @click="navigator.clipboard.writeText('{{ session('issued_m2m_client')['client_secret'] }}'); copiedSecret = true; setTimeout(() => copiedSecret = false, 3000)" class="px-2 py-1 bg-[#1A1A1A] border border-purple-500/40 text-purple-400 rounded text-[10px] font-bold">
                    <span x-text="copiedSecret ? 'Copiado' : 'Copiar'"></span>
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Tab Navigation -->
    <div class="flex items-center gap-2 border-b border-gray-800 pb-2">
        <button type="button" @click="activeTab = 'personal'" :class="activeTab === 'personal' ? 'bg-[#1e1e1e] text-[#00FF99] border-[#00FF99]' : 'text-gray-400 hover:text-white border-transparent'" class="px-4 py-2 rounded-lg border text-xs font-bold transition-colors flex items-center gap-2">
            <i class="fa-solid fa-user"></i>
            <span>{{ __('admin.tokens.pat_tab') }} ({{ $tokens->total() }})</span>
        </button>
        <button type="button" @click="activeTab = 'm2m'" :class="activeTab === 'm2m' ? 'bg-[#1e1e1e] text-purple-400 border-purple-500' : 'text-gray-400 hover:text-white border-transparent'" class="px-4 py-2 rounded-lg border text-xs font-bold transition-colors flex items-center gap-2">
            <i class="fa-solid fa-robot"></i>
            <span>{{ __('admin.tokens.m2m_tab') }} ({{ $clients->total() }})</span>
        </button>
    </div>

    <!-- Tab 1: Personal Access Tokens Table -->
    <div x-show="activeTab === 'personal'">
        <x-admin.table
            :empty="$tokens->isEmpty()"
            emptyTitle="No hay Personal Access Tokens"
            emptyDescription="No hay Personal Access Tokens registrados actualmente."
            :emptyActionRoute="route('admin.tokens.create')"
            emptyActionLabel="+ Crear Token"
        >
            <x-slot:header>
                <th class="py-3 px-2 md:hidden w-8"></th>
                <th class="py-3 px-4 uppercase font-bold text-[11px]">{{ __('admin.tokens.col_token_name') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden sm:table-cell">{{ __('admin.tokens.col_subject') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden md:table-cell">{{ __('admin.tokens.col_scopes') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden lg:table-cell">{{ __('admin.tokens.col_created') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden sm:table-cell">{{ __('admin.tokens.col_status') }}</th>
                <th class="py-3 px-4 text-right uppercase font-bold text-[11px]">{{ __('admin.tokens.col_actions') }}</th>
            </x-slot:header>

            @foreach($tokens as $t)
                <tr class="hover:bg-gray-800/30 transition-colors">
                    <td class="py-3 px-2 md:hidden text-center w-8">
                        <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-gray-400 hover:text-white p-1 focus:outline-none" aria-label="Ver detalles">
                            <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[#00FF99]' : 'fa-chevron-down'"></i>
                        </button>
                    </td>
                    <td class="py-3 px-4 font-bold text-white">
                        {{ $t->name }}
                        <div class="text-[10px] text-gray-500 font-mono">ID: {{ substr($t->id, 0, 16) }}...</div>
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        @if($t->user_id && isset($users[$t->user_id]))
                            <span class="px-2 py-0.5 rounded bg-blue-500/10 border border-blue-500/30 text-blue-400 text-[10px] inline-flex items-center gap-1">
                                <i class="fa-solid fa-user text-[10px]"></i> {{ $users[$t->user_id]->name }}
                            </span>
                        @elseif($t->user_id)
                            <span class="px-2 py-0.5 rounded bg-green-500/10 border border-green-500/30 text-green-400 text-[10px] inline-flex items-center gap-1">
                                <i class="fa-solid fa-bag-shopping text-[10px]"></i> Sujeto #{{ $t->user_id }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded bg-purple-500/10 border border-purple-500/30 text-purple-400 text-[10px] inline-flex items-center gap-1">
                                <i class="fa-solid fa-robot text-[10px]"></i> Machine-to-Machine
                            </span>
                        @endif
                    </td>
                    <td class="py-3 px-4 hidden md:table-cell">
                        <div class="flex flex-wrap gap-1">
                            @forelse($t->scopes as $s)
                                <x-admin.chip>{{ $s }}</x-admin.chip>
                            @empty
                                <span class="text-gray-500 text-[10px]">{{ __('admin.tokens.none') }}</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="py-3 px-4 text-gray-400 text-[11px] hidden lg:table-cell">
                        {{ $t->created_at?->format('Y-m-d H:i') ?? '-' }}
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        <x-admin.status-badge :active="! $t->revoked" activeText="Activo" inactiveText="Revocado" />
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            @if(! $t->revoked)
                                <x-admin.action-button
                                    icon="fa-solid fa-ban"
                                    tooltip="Revocar Token"
                                    variant="danger"
                                    @click="$dispatch('confirm-delete', {
                                        action: '{{ route('admin.tokens.revoke', $t->id) }}',
                                        title: 'Revocar Token',
                                        message: '¿Seguro que deseas revocar este token de acceso de inmediato?'
                                    })"
                                />
                            @else
                                <span class="text-gray-600 text-[11px]">-</span>
                            @endif
                        </div>
                    </td>
                </tr>

                <x-admin.table-row-detail colSpan="7">
                    <x-admin.table-row-detail-item label="{{ __('admin.tokens.col_subject') }}">
                        @if($t->user_id && isset($users[$t->user_id]))
                            <span class="text-blue-400 font-mono">{{ $users[$t->user_id]->name }} ({{ $users[$t->user_id]->email }})</span>
                        @elseif($t->user_id)
                            <span class="text-green-400 font-mono">{{ __('admin.tokens.subject_module', ['id' => $t->user_id]) }}</span>
                        @else
                            <span class="text-purple-400 font-mono">{{ __('admin.tokens.m2m') }}</span>
                        @endif
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Scopes Concedidos">
                        <div class="flex flex-wrap gap-1">
                            @forelse($t->scopes as $s)
                                <x-admin.chip>{{ $s }}</x-admin.chip>
                            @empty
                                <span class="text-gray-500 text-[10px]">{{ __('admin.tokens.none') }}</span>
                            @endforelse
                        </div>
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Estado">
                        <x-admin.status-badge :active="! $t->revoked" activeText="Activo" inactiveText="Revocado" />
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Fecha de creación">
                        <span class="text-gray-400">{{ $t->created_at?->format('Y-m-d H:i') ?? '-' }}</span>
                    </x-admin.table-row-detail-item>
                </x-admin.table-row-detail>
            @endforeach

            <x-slot:pagination>
                {{ $tokens->links() }}
            </x-slot:pagination>
        </x-admin.table>
    </div>

    <!-- Tab 2: M2M Clients Table -->
    <div x-show="activeTab === 'm2m'">
        <x-admin.table
            :empty="$clients->isEmpty()"
            emptyTitle="No hay clientes M2M"
            emptyDescription="No hay clientes M2M registrados actualmente."
            :emptyActionRoute="route('admin.tokens.create')"
            emptyActionLabel="+ Crear Cliente"
        >
            <x-slot:header>
                <th class="py-3 px-2 md:hidden w-8"></th>
                <th class="py-3 px-4 uppercase font-bold text-[11px]">{{ __('admin.tokens.col_app_name') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden sm:table-cell">{{ __('admin.tokens.col_client_id') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden md:table-cell">{{ __('admin.tokens.col_auth_provider') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden lg:table-cell">{{ __('admin.tokens.col_created') }}</th>
                <th class="py-3 px-4 uppercase font-bold text-[11px] hidden sm:table-cell">{{ __('admin.tokens.col_status') }}</th>
                <th class="py-3 px-4 text-right uppercase font-bold text-[11px]">{{ __('admin.tokens.col_actions') }}</th>
            </x-slot:header>

            @foreach($clients as $c)
                <tr class="hover:bg-gray-800/30 transition-colors">
                    <td class="py-3.5 px-2 md:hidden text-center w-8">
                        <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-gray-400 hover:text-white p-1 focus:outline-none" aria-label="Ver detalles">
                            <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[#00FF99]' : 'fa-chevron-down'"></i>
                        </button>
                    </td>
                    <td class="py-3 px-4 font-bold text-white">
                        {{ $c->name }}
                        <div class="sm:hidden font-mono text-purple-400 text-[10px] mt-0.5 select-all">{{ $c->id }}</div>
                    </td>
                    <td class="py-3 px-4 font-mono text-purple-400 text-[11px] select-all hidden sm:table-cell">
                        {{ $c->id }}
                    </td>
                    <td class="py-3 px-4 text-gray-400 font-mono text-[11px] hidden md:table-cell">
                        {{ $c->provider ?? 'users' }}
                    </td>
                    <td class="py-3 px-4 text-gray-400 text-[11px] hidden lg:table-cell">
                        {{ $c->created_at?->format('Y-m-d H:i') ?? '-' }}
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        <x-admin.status-badge :active="! $c->revoked" activeText="Activo" inactiveText="Revocado" />
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            @if(! $c->revoked)
                                <x-admin.action-button
                                    icon="fa-solid fa-ban"
                                    tooltip="Revocar Cliente"
                                    variant="danger"
                                    @click="$dispatch('confirm-delete', {
                                        action: '{{ route('admin.tokens.revoke-client', $c->id) }}',
                                        title: 'Revocar Cliente OAuth2 M2M',
                                        message: '¿Seguro que deseas revocar este cliente OAuth2 M2M?'
                                    })"
                                />
                            @else
                                <span class="text-gray-600 text-[11px]">-</span>
                            @endif
                        </div>
                    </td>
                </tr>

                <x-admin.table-row-detail colSpan="7">
                    <x-admin.table-row-detail-item label="Client ID (OAuth2)">
                        <span class="font-mono text-purple-400 select-all">{{ $c->id }}</span>
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Proveedor Auth">
                        <span class="font-mono text-gray-300">{{ $c->provider ?? 'users' }}</span>
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Estado">
                        <x-admin.status-badge :active="! $c->revoked" activeText="Activo" inactiveText="Revocado" />
                    </x-admin.table-row-detail-item>
                    <x-admin.table-row-detail-item label="Fecha de creación">
                        <span class="text-gray-400">{{ $c->created_at?->format('Y-m-d H:i') ?? '-' }}</span>
                    </x-admin.table-row-detail-item>
                </x-admin.table-row-detail>
            @endforeach

            <x-slot:pagination>
                {{ $clients->links() }}
            </x-slot:pagination>
        </x-admin.table>
    </div>

</div>
@endsection
