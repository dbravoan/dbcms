@extends('admin.layout')

@section('title', __('admin.page_titles.form_submissions'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs" x-data="{ payloadModal: null }">

    <!-- Unified Datatable Header -->
    <x-admin.datatable-header
        title="{{ __('admin.forms_ui.submissions_title') }}"
        description="{{ __('admin.forms_ui.submissions_desc') }}"
        search-placeholder="{{ __('admin.forms_ui.ph_ip') }}"
    >
        <!-- Filter: Form -->
        <x-admin.search-select source="forms" name="form_id" value-key="id" :value="request('form_id')" placeholder="{{ __('admin.forms_ui.ph_all_forms') }}" />
    </x-admin.datatable-header>

    <!-- Submissions Table -->
    <x-admin.table
        :empty="$submissions->isEmpty()"
        emptyTitle="{{ __('admin.forms_ui.submissions_empty_title') }}"
        emptyDescription="{{ __('admin.forms_ui.submissions_empty_desc') }}"
    >
        <x-slot:header>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.forms_ui.col_form') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.forms_ui.summary') }}</th>
            <x-admin.sortable-th column="ip_address" label="{{ __('admin.forms_ui.label_ip') }}" />
            <x-admin.sortable-th column="created_at" label="{{ __('admin.forms_ui.label_date') }}" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($submissions as $s)
            <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                <td class="py-3.5 px-4 font-bold text-[color:var(--admin-accent)]">
                    {{ $s->form->name ?? 'Formulario #' . $s->custom_form_id }}
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-text)] max-w-sm truncate">
                    @php
                        $summary = collect($s->payload)->map(fn ($v, $k) => "$k: $v")->implode(' | ');
                    @endphp
                    {{ $summary ?: 'Sin campos' }}
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-muted)]">{{ $s->ip_address ?? '127.0.0.1' }}</td>
                <td class="py-3.5 px-4 text-[color:var(--admin-muted)]">{{ $s->created_at->format('Y-m-d H:i') }}</td>
                <td class="py-3.5 px-4 text-right space-x-3">
                    <button type="button"
                            @click="payloadModal = {{ json_encode($s->payload) }}"
                            class="text-blue-400 hover:text-blue-300 hover:underline">
                        Ver Detalle
                    </button>
                    <button type="button"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.forms.submissions.destroy', $s) }}',
                                title: 'Eliminar Lead',
                                message: '¿Estás seguro de eliminar este envío recibido el {{ $s->created_at->format('Y-m-d H:i') }}?'
                            })"
                            class="text-red-400 hover:text-red-300 hover:underline">
                        Eliminar
                    </button>
                </td>
            </tr>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$submissions" />
        </x-slot:pagination>
    </x-admin.table>

    <!-- Payload Inspector Modal -->
    <x-admin.modal
        show="payloadModal !== null"
        onClose="payloadModal = null"
        title="{{ __('admin.forms_ui.detail_title') }}"
        subtitle="{{ __('admin.forms_ui.detail_subtitle') }}"
        icon="fa-solid fa-address-card"
        maxWidth="lg"
    >
        <div class="bg-[color:var(--admin-surface)] p-4 rounded-xl border border-[color:var(--admin-border)] space-y-2 overflow-y-auto max-h-80">
            <template x-for="(value, key) in payloadModal" :key="key">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-1 pb-2 border-b border-[color:var(--admin-border)]/40 text-xs">
                    <span class="text-[color:var(--admin-muted)] font-bold uppercase text-[10px]" x-text="key"></span>
                    <span class="text-[color:var(--admin-text)] break-all font-mono" x-text="value"></span>
                </div>
            </template>
        </div>

        <x-slot:footer>
            <button type="button" @click="payloadModal = null" class="px-4 py-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] hover:text-[color:var(--admin-text)] rounded-lg text-xs font-bold transition-colors">
                Cerrar
            </button>
        </x-slot:footer>
    </x-admin.modal>
</div>
@endsection
