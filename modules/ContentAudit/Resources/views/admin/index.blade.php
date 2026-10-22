@extends('admin.layout')

@section('title', __('admin.page_titles.content_audit'))

@section('content')
<div class="admin-page space-y-6 font-mono" x-data="{ activeTab: @js($currentTab) }">
    <!-- Header Title & Metric Cards -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-[color:var(--admin-surface)] p-5 rounded-xl border border-[color:var(--admin-border)]">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-clipboard-check text-[color:var(--admin-accent)]"></i>
                <span>{{ __('admin.content_audit.title') }}</span>
            </h1>
            <p class="text-xs text-[color:var(--admin-muted)] mt-1">
                Control de cambios, flujo de validación previa a publicación y trazabilidad de versiones. Las valoraciones de estilo requieren criterio editorial y comprobación de fuentes.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if($pendingCount > 0)
                <span class="px-3 py-1 bg-amber-500/15 border border-amber-500/40 text-amber-400 rounded-lg text-xs font-bold animate-pulse flex items-center gap-1.5">
                    <i class="fa-solid fa-bell"></i>
                    <span>{{ $pendingCount }} Pendientes de Revisión</span>
                </span>
            @else
                <span class="px-3 py-1 bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 rounded-lg text-xs font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-check-double"></i>
                    <span>{{ __('admin.content_audit.queue_up_to_date') }}</span>
                </span>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs rounded-xl flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-[color:var(--admin-border)] pb-3">
        <button type="button" @click="activeTab = 'queue'"
                :class="activeTab === 'queue' ? 'bg-[color:var(--admin-accent)] text-black font-bold border-[color:var(--admin-accent)]' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] border-[color:var(--admin-border)]'"
                class="px-4 py-2 rounded-lg text-xs border transition-all flex items-center gap-2">
            <i class="fa-solid fa-inbox"></i>
            <span>{{ __('admin.content_audit.tab_queue') }}</span>
            @if($pendingCount > 0)
                <span :class="activeTab === 'queue' ? 'bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)]' : 'bg-amber-500 text-black'"
                      class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold">
                    {{ $pendingCount }}
                </span>
            @endif
        </button>

        <button type="button" @click="activeTab = 'changelog'"
                :class="activeTab === 'changelog' ? 'bg-[color:var(--admin-accent)] text-black font-bold border-[color:var(--admin-accent)]' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] border-[color:var(--admin-border)]'"
                class="px-4 py-2 rounded-lg text-xs border transition-all flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>{{ __('admin.content_audit.tab_changelog') }}</span>
        </button>

        @if($canManageSettings)
        <button type="button" @click="activeTab = 'settings'"
                :class="activeTab === 'settings' ? 'bg-[color:var(--admin-accent)] text-black font-bold border-[color:var(--admin-accent)]' : 'bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] border-[color:var(--admin-border)]'"
                class="px-4 py-2 rounded-lg text-xs border transition-all flex items-center gap-2 ml-auto">
            <i class="fa-solid fa-sliders"></i>
            <span>{{ __('admin.content_audit.tab_settings') }}</span>
        </button>
        @endif
    </div>

    <!-- TAB 1: COLA DE APROBACIÓN DE CALIDAD -->
    <div x-show="activeTab === 'queue'" class="space-y-4">
        <!-- Filter Bar -->
        <div class="bg-[color:var(--admin-surface)] p-4 rounded-xl border border-[color:var(--admin-border)] flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="text-[color:var(--admin-muted)] font-bold uppercase text-[10px]">{{ __('admin.content_audit.filter_status') }}</span>
                <a href="{{ route('admin.audit.index', ['tab' => 'queue', 'status' => 'pending']) }}"
                   class="px-2.5 py-1 rounded {{ $statusFilter === 'pending' ? 'bg-[color:var(--admin-accent)]/20 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/40 font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]' }}">
                    Pendientes
                </a>
                <a href="{{ route('admin.audit.index', ['tab' => 'queue', 'status' => 'approved']) }}"
                   class="px-2.5 py-1 rounded {{ $statusFilter === 'approved' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]' }}">
                    Aprobados
                </a>
                <a href="{{ route('admin.audit.index', ['tab' => 'queue', 'status' => 'rejected']) }}"
                   class="px-2.5 py-1 rounded {{ $statusFilter === 'rejected' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/40 font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]' }}">
                    Rechazados
                </a>
                <a href="{{ route('admin.audit.index', ['tab' => 'queue', 'status' => 'all']) }}"
                   class="px-2.5 py-1 rounded {{ $statusFilter === 'all' ? 'bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]' }}">
                    Todos
                </a>
            </div>
        </div>

        <!-- Table of Audits -->
        <x-admin.table
            :empty="$audits->isEmpty()"
            emptyTitle="No hay contenidos en esta cola de auditoría"
            emptyDescription="No hay contenidos con el filtro de estado seleccionado."
        >
            <x-slot:header>
                <th class="py-3 px-4">{{ __('admin.content_audit.col_entity') }}</th>
                <th class="py-3 px-4">{{ __('admin.content_audit.col_title_content') }}</th>
                <th class="py-3 px-4">{{ __('admin.content_audit.col_author') }}</th>
                <th class="py-3 px-4">{{ __('admin.content_audit.col_quality_status') }}</th>
                <th class="py-3 px-4">{{ __('admin.content_audit.col_reviewer') }}</th>
                <th class="py-3 px-4 text-right">{{ __('admin.content_audit.col_actions') }}</th>
            </x-slot:header>

            @foreach($audits as $item)
                @php
                    $statusEnum = $item->getStatusEnum();
                    $typeSlug = Str::plural(Str::snake(class_basename($item->auditable_type)));
                    $title = $item->entity_title ?: "Elemento #{$item->auditable_id}";
                @endphp
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="py-3 px-4">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[color:var(--admin-surface)]/60 text-[color:var(--admin-text)] border border-[color:var(--admin-border)] uppercase">
                            {{ class_basename($item->auditable_type) }} #{{ $item->auditable_id }}
                        </span>
                    </td>
                    <td class="py-3 px-4 font-bold text-[color:var(--admin-text)] max-w-xs truncate">
                        {{ $title }}
                    </td>
                    <td class="py-3 px-4 space-y-0.5">
                        <div class="text-[color:var(--admin-text)] font-semibold">{{ $item->submitter?->name ?? __('admin.content_audit.unknown') }}</div>
                        <div class="text-[10px] text-[color:var(--admin-muted)]">{{ $item->submitted_at?->format('d/m/Y H:i') ?? '-' }}</div>
                    </td>
                    <td class="py-3 px-4">
                        <span class="px-2.5 py-1 rounded text-[10px] font-bold inline-flex items-center gap-1 {{ $statusEnum->badgeClass() }}">
                            <i class="fa-solid {{ $statusEnum->icon() }} text-[9px]"></i>
                            {{ $statusEnum->label() }}
                        </span>
                    </td>
                    <td class="py-3 px-4 space-y-0.5">
                        @if($item->reviewer)
                            <div class="text-[color:var(--admin-text)]">{{ $item->reviewer->name }}</div>
                            <div class="text-[10px] text-[color:var(--admin-muted)]">{{ $item->reviewed_at?->format('d/m/Y H:i') }}</div>
                        @else
                            <span class="text-[color:var(--admin-muted)] italic">-</span>
                        @endif
                    </td>
                    <td class="py-3 px-4 text-right">
                        <a href="{{ route('admin.audit.review', ['type' => $typeSlug, 'id' => $item->auditable_id]) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[color:var(--admin-accent)]/15 hover:bg-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/40 hover:border-[color:var(--admin-accent)] text-[color:var(--admin-accent)] hover:text-black rounded text-xs font-bold transition-all">
                            <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                            <span>{{ __('admin.content_audit.review_judge') }}</span>
                        </a>
                    </td>
                </tr>
            @endforeach

            <x-slot:pagination>
                <x-admin.datatable-pagination :paginator="$audits" />
            </x-slot:pagination>
        </x-admin.table>
    </div>

    <!-- TAB 2: CHANGELOG GLOBAL & CONTROL DE CAMBIOS -->
    <div x-show="activeTab === 'changelog'" class="space-y-4">
        <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]">
                <div>
                    <h2 class="text-sm font-bold text-[color:var(--admin-text)] uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-[color:var(--admin-accent)]"></i>
                        <span>{{ __('admin.content_audit.changelog_title') }}</span>
                    </h2>
                    <p class="text-[11px] text-[color:var(--admin-muted)] mt-1">
                        Historial inmutable con deltas y snapshots de cada cambio sobre artículos, páginas y productos.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-[color:var(--admin-text)]">
                    <thead class="bg-[color:var(--admin-surface-raised)]/80 text-[11px] uppercase text-[color:var(--admin-muted)] border-b border-[color:var(--admin-border)]">
                        <tr>
                            <th class="py-3 px-4">{{ __('admin.content_audit.date_time') }}</th>
                            <th class="py-3 px-4">{{ __('admin.content_audit.version') }}</th>
                            <th class="py-3 px-4">{{ __('admin.content_audit.review_action') }}</th>
                            <th class="py-3 px-4">{{ __('admin.content_audit.col_entity_title') }}</th>
                            <th class="py-3 px-4">{{ __('admin.content_audit.user') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('admin.content_audit.detail_diff') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color:var(--admin-border)]/60">
                        @forelse($revisions as $rev)
                            @php
                                $actionEnum = $rev->getActionEnum();
                            @endphp
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3 px-4 text-[color:var(--admin-muted)] whitespace-nowrap">
                                    {{ $rev->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-[color:var(--admin-surface)]/60 border border-[color:var(--admin-border)] text-[color:var(--admin-text)]">
                                        v{{ $rev->version }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] inline-flex items-center gap-1">
                                        <i class="fa-solid {{ $actionEnum->icon() }} text-[9px]"></i>
                                        {{ $actionEnum->label() }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-[color:var(--admin-text)]">{{ $rev->title }}</div>
                                    <div class="text-[10px] text-[color:var(--admin-muted)]">{{ class_basename($rev->auditable_type) }} #{{ $rev->auditable_id }}</div>
                                </td>
                                <td class="py-3 px-4 text-[color:var(--admin-text)]">
                                    {{ $rev->user?->name ?? 'Sistema' }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('admin.audit.diff', $rev->id) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] border border-[color:var(--admin-border)] text-[11px] transition-all">
                                        <i class="fa-solid fa-code-compare text-[10px] text-[color:var(--admin-accent)]"></i>
                                        <span>{{ __('admin.content_audit.inspect_diff') }}</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-[color:var(--admin-muted)] text-xs">
                                    No se han registrado modificaciones todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($revisions->hasPages())
                <div class="p-4 border-t border-[color:var(--admin-border)]">
                    {{ $revisions->appends(['tab' => 'changelog'])->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- TAB 3: AJUSTES & POLÍTICAS DEL MÓDULO -->
    @if($canManageSettings)
    <div x-show="activeTab === 'settings'" class="space-y-4">
        <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-6 max-w-4xl shadow-xl">
            <div class="pb-4 border-b border-[color:var(--admin-border)] mb-6">
                <h2 class="text-base font-bold text-[color:var(--admin-text)] uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-[color:var(--admin-accent)]"></i>
                    <span>{{ __('admin.content_audit.policies_title') }}</span>
                </h2>
                <p class="text-xs text-[color:var(--admin-muted)] mt-1">
                    Define qué roles están obligados a solicitar revisión y quiénes pueden dictaminar la publicación.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.audit.settings') }}" class="space-y-6">
                @csrf

                <!-- Enable Module Switch -->
                <div class="flex items-center justify-between p-4 bg-[color:var(--admin-surface)] rounded-lg border border-[color:var(--admin-border)]">
                    <div>
                        <span class="block text-xs font-bold text-[color:var(--admin-text)] uppercase">{{ __('admin.content_audit.enable_audit') }}</span>
                        <span class="block text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.content_audit.enable_audit_hint') }}</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" class="sr-only peer" {{ ($settings['is_enabled'] ?? true) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-[color:var(--admin-surface-raised)] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-[color:var(--admin-border)] after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[color:var(--admin-accent)]"></div>
                    </label>
                </div>

                <!-- Roles Restringidos -->
                <div>
                    <x-admin.role-multiselect
                        name="audited_roles"
                        :selected="$settings['audited_roles'] ?? 'author,marketing'"
                        label="Roles Sujetos a Auditoría Previa (Sin Publicación Directa)"
                        help="Los usuarios con estos roles verán el botón 'Enviar a Revisión' y sus cambios deberán ser validados por un auditor."
                    />
                </div>

                <!-- Roles Aprobadores -->
                <div>
                    <x-admin.role-multiselect
                        name="approver_roles"
                        :selected="$settings['approver_roles'] ?? 'admin,editor'"
                        label="Roles Auditores / Aprobadores de Calidad"
                        help="Roles facultados para inspeccionar el historial de cambios, comparar versiones, aprobar o rechazar publicaciones."
                    />
                </div>

                <!-- Entidades Auditadas -->
                <div>
                    <x-admin.entity-multiselect
                        name="audited_entities"
                        :selected="$settings['audited_entities'] ?? 'posts,pages,products,galleries'"
                        label="Entidades Bajo Flujo de Auditoría"
                        help="Modelos y tipos de contenido del sistema donde se aplicará el flujo de control de calidad previo."
                    />
                </div>

                <div class="space-y-4">
                    <label class="admin-field-label flex items-center gap-3">
                        <input type="checkbox" name="enforce_editorial_quality" value="1" class="admin-checkbox" {{ ($settings['enforce_editorial_quality'] ?? false) ? 'checked' : '' }}>
                        Exigir revisión de estilo a los roles sujetos a auditoría
                    </label>
                    <x-admin.input name="min_anti_ai_score" type="number" label="Umbral de estilo (0–100)" :value="$settings['min_anti_ai_score'] ?? 85" min="0" max="100" step="1" hint="Heurística de redacción: no verifica hechos ni identifica si el texto fue escrito por una persona o una IA." />
                    <label class="admin-field-label flex items-center gap-3">
                        <input type="checkbox" name="notify_email" value="1" class="admin-checkbox" {{ ($settings['notify_email'] ?? false) ? 'checked' : '' }}>
                        Enviar avisos por correo cuando exista un transporte configurado
                    </label>
                </div>

                <!-- Additional Toggles -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="flex items-center gap-3 p-3 bg-[color:var(--admin-surface)] rounded-lg border border-[color:var(--admin-border)] text-xs cursor-pointer">
                        <input type="checkbox" name="auto_publish_on_approval" value="1" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0" {{ ($settings['auto_publish_on_approval'] ?? true) ? 'checked' : '' }}>
                        <span class="text-[color:var(--admin-text)]">{{ __('admin.content_audit.auto_publish') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 bg-[color:var(--admin-surface)] rounded-lg border border-[color:var(--admin-border)] text-xs cursor-pointer">
                        <input type="checkbox" name="track_all_modifications" value="1" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0" {{ ($settings['track_all_modifications'] ?? true) ? 'checked' : '' }}>
                        <span class="text-[color:var(--admin-text)]">{{ __('admin.content_audit.track_admin') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 bg-[color:var(--admin-surface)] rounded-lg border border-[color:var(--admin-border)] text-xs cursor-pointer">
                        <input type="checkbox" name="require_rejection_reason" value="1" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0" {{ ($settings['require_rejection_reason'] ?? true) ? 'checked' : '' }}>
                        <span class="text-[color:var(--admin-text)]">{{ __('admin.content_audit.require_reason') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 bg-[color:var(--admin-surface)] rounded-lg border border-[color:var(--admin-border)] text-xs cursor-pointer">
                        <input type="checkbox" name="notify_in_app" value="1" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0" {{ ($settings['notify_in_app'] ?? true) ? 'checked' : '' }}>
                        <span class="text-[color:var(--admin-text)]">{{ __('admin.content_audit.notify_inapp') }}</span>
                    </label>
                </div>

                <x-admin.form-actions :submit="false" class="pt-4 border-t border-[color:var(--admin-border)]">
                    <x-admin.button type="submit" variant="primary">Guardar políticas de auditoría</x-admin.button>
                </x-admin.form-actions>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
