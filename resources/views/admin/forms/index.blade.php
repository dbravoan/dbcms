@extends('admin.layout')

@section('title', __('admin.page_titles.forms'))

@php
    $primaryLocale = $activeLanguages->first()->code ?? 'es';
@endphp

@section('content')
<div class="space-y-6 font-mono text-xs" x-data="{ showCreateModal: false, previewFormId: null }">

    <!-- Unified Datatable Header -->
    <x-admin.datatable-header
        title="{{ __('admin.forms_ui.title') }}"
        description="{{ __('admin.forms_ui.desc') }}"
        searchPlaceholder="{{ __('admin.forms_ui.search_placeholder') }}"
        createRoute="{{ route('admin.forms.create') }}"
        createLabel="{{ __('admin.forms_ui.create_label') }}"
    >
        <!-- Filter: Status -->
        <select name="is_active" class="bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            <option value="all">{{ __('admin.forms_ui.all_statuses') }}</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('admin.listing.active_m') }}</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('admin.listing.inactive_m') }}</option>
        </select>

        <x-admin.market-select name="market_id" value-key="id" :value="request('market_id')" placeholder="{{ __('admin.listing.all_markets_cap') }}" />
    </x-admin.datatable-header>

    <!-- Forms Table -->
    <x-admin.table
        :empty="$forms->isEmpty()"
        emptyTitle="{{ __('admin.forms_ui.empty_title') }}"
        emptyDescription="{{ __('admin.forms_ui.empty_desc') }}"
        :emptyActionRoute="route('admin.forms.create')"
        emptyActionLabel="+ Diseñar Formulario"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <x-admin.sortable-th column="slug" label="{{ __('admin.listing.name') }}" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden sm:table-cell">{{ __('admin.forms_ui.col_slug_preview') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden md:table-cell">{{ __('admin.forms_ui.col_fields') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden lg:table-cell">{{ __('admin.listing.markets') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden xl:table-cell">{{ __('admin.listing.languages') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden sm:table-cell">{{ __('admin.forms_ui.col_submissions') }}</th>
            <x-admin.sortable-th column="is_active" label="{{ __('admin.listing.status') }}" class="hidden md:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-400">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($forms as $f)
            <tr class="hover:bg-gray-800/30 transition-colors">
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-gray-400 hover:text-white p-1 focus:outline-none" aria-label="{{ __('admin.listing.view_details') }}">
                        <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[#00FF99]' : 'fa-chevron-down'"></i>
                    </button>
                </td>
                <td class="py-3.5 px-4 font-bold text-white">
                    <a href="{{ route('admin.forms.edit', $f) }}" class="hover:text-[#00FF99] transition-colors">
                        {{ $f->name }}
                    </a>
                </td>
                <td class="py-3.5 px-4 hidden sm:table-cell">
                    <div class="flex flex-col items-start gap-1">
                        <button type="button"
                                @click="previewFormId = {{ $f->id }}"
                                class="px-2.5 py-1 bg-[#1A1A1A] hover:bg-[#00FF99]/10 text-gray-200 hover:text-[#00FF99] border border-gray-700 hover:border-[#00FF99]/40 rounded-lg text-xs font-mono inline-flex items-center gap-1.5 transition-all cursor-pointer shadow-sm group">
                            <i class="fa-solid fa-eye text-[#00FF99] group-hover:scale-110 transition-transform"></i>
                            <span>{{ __('admin.forms_ui.view_form') }}</span>
                        </button>
                        <span class="text-[10px] text-gray-500 font-mono">#{{ $f->slug }}</span>
                    </div>
                </td>
                <td class="py-3.5 px-4 text-gray-300 hidden md:table-cell">
                    <div class="flex items-center gap-2">
                        <x-admin.chip>{{ $f->fields_count }} campos</x-admin.chip>
                        @if($f->fields->contains('type', 'captcha') && ! captcha_available())
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-amber-950/50 text-amber-400 border border-amber-800/50"
                                  title="{{ __('admin.forms_ui.captcha_module_inactive') }}">
                                Captcha
                            </span>
                        @endif
                    </div>
                </td>
                <td class="py-3.5 px-4 hidden lg:table-cell">
                    <x-admin.market-chips :markets="$f->markets" />
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.locale-chips :locales="$f->translations->pluck('locale')" />
                </td>
                <td class="py-3.5 px-4 hidden sm:table-cell">
                    <x-admin.chip>{{ $f->submissions_count }} leads</x-admin.chip>
                </td>
                <td class="py-3.5 px-4 hidden md:table-cell">
                    <x-admin.status-badge :active="$f->is_active" activeText="Activo" inactiveText="Inactivo" />
                </td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        <x-admin.action-button
                            as="a"
                            href="{{ route('admin.forms.edit', $f) }}"
                            icon="fa-solid fa-pen-to-square"
                            tooltip="Editar formulario y campos"
                            variant="primary"
                        />
                        <x-admin.action-button
                            as="a"
                            href="{{ route('admin.forms.submissions', ['form_id' => $f->id]) }}"
                            icon="fa-solid fa-inbox"
                            tooltip="Ver leads recibidos"
                            variant="view"
                        />
                        <x-admin.action-button
                            type="button"
                            @click="previewFormId = {{ $f->id }}"
                            icon="fa-solid fa-eye"
                            tooltip="Vista previa interactiva"
                            variant="default"
                        />
                        <x-admin.action-button
                            icon="fa-solid fa-trash-can"
                            tooltip="Eliminar formulario"
                            variant="danger"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.forms.destroy', $f) }}',
                                title: 'Eliminar Formulario',
                                message: '¿Estás seguro de eliminar este formulario y todos sus envíos?'
                            })"
                        />
                    </div>
                </td>
            </tr>

            <x-admin.table-row-detail colSpan="8">
                <x-admin.table-row-detail-item label="{{ __('admin.forms_ui.col_slug_preview') }}">
                    <span class="font-mono text-[#00FF99]">#{{ $f->slug }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.forms_ui.col_fields') }}">
                    <div class="flex items-center gap-2">
                        <x-admin.chip>{{ $f->fields_count }} campos</x-admin.chip>
                        @if($f->fields->contains('type', 'captcha') && ! captcha_available())
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-amber-950/50 text-amber-400 border border-amber-800/50"
                                  title="{{ __('admin.forms_ui.captcha_module_inactive') }}">
                                Captcha
                            </span>
                        @endif
                    </div>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.enabled_markets') }}">
                    <x-admin.market-chips :markets="$f->markets" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.languages') }}">
                    <x-admin.locale-chips :locales="$f->translations->pluck('locale')" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.forms_ui.col_submissions') }}">
                    <x-admin.chip>{{ $f->submissions_count }} leads</x-admin.chip>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.status') }}">
                    <x-admin.status-badge :active="$f->is_active" activeText="Activo" inactiveText="Inactivo" />
                </x-admin.table-row-detail-item>
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$forms" />
        </x-slot:pagination>
    </x-admin.table>

    <!-- Rendered Form Preview Modals -->
    @foreach($forms as $f)
        <!-- Form Live Preview Modal -->
        <x-admin.modal
            show="previewFormId === {{ $f->id }}"
            onClose="previewFormId = null"
            maxWidth="2xl"
            icon="fa-solid fa-eye"
        >
            <x-slot:header>
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#00FF99] animate-pulse"></span>
                    <h3 class="text-sm font-bold text-white tracking-tight">Vista Previa: {{ $f->name }}</h3>
                    <span class="text-[10px] font-mono text-[#00FF99] bg-[#00FF99]/10 border border-[#00FF99]/30 px-2 py-0.5 rounded">#{{ $f->slug }}</span>
                </div>
            </x-slot:header>

            <!-- Embed Snippet Helper -->
            <div class="bg-[#181818] border border-gray-800 rounded-lg p-3 text-[11px] text-gray-300 flex items-center justify-between flex-wrap gap-2 shrink-0">
                <span class="text-gray-400">{{ __('admin.forms_ui.embed_hint') }}</span>
                <code class="text-[#00FF99] bg-black/50 border border-gray-800 px-2.5 py-1 rounded text-xs select-all">&lt;x-cms.form-engine slug="{{ $f->slug }}" /&gt;</code>
            </div>

            <!-- Rendered Form Preview Content -->
            <div class="overflow-y-auto pr-1 space-y-4 rounded-xl border border-gray-800 bg-[#161616] p-5">
                <x-cms.form-engine :form="$f" :actionUrl="'javascript:void(0)'" :showCard="false" />
            </div>

            <x-slot:footer>
                <a href="{{ route('admin.forms.edit', $f) }}" class="text-xs text-[#00FF99] hover:underline inline-flex items-center gap-1.5 font-bold mr-auto">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <span>{{ __('admin.forms_ui.edit_fields') }}</span>
                </a>

                <button type="button" @click="previewFormId = null" class="px-4 py-2 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white rounded-lg text-xs font-bold transition-colors cursor-pointer">
                    Cerrar
                </button>
            </x-slot:footer>
        </x-admin.modal>
    @endforeach

    <!-- Create Form Modal -->
    <div x-data="{ open: false }" @open-create-form-modal.window="open = true">
        <x-admin.modal
            show="open"
            title="{{ __('admin.forms_ui.create_modal_title') }}"
            subtitle="{{ __('admin.forms_ui.create_modal_subtitle') }}"
            icon="fa-solid fa-clipboard-list"
            maxWidth="md"
        >
            <form method="POST" action="{{ route('admin.forms.store') }}" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-gray-400 uppercase mb-1">Nombre ({{ strtoupper((string) $primaryLocale) }})</label>
                    <input type="text" name="translations[{{ $primaryLocale }}][name]" required placeholder="{{ __('admin.forms_ui.ph_name') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block font-bold text-gray-400 uppercase mb-1">{{ __('admin.forms_ui.col_slug_preview') }}</label>
                    <input type="text" name="slug" placeholder="{{ __('admin.forms_ui.ph_slug') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-mono">
                </div>
                <div>
                    <label class="block font-bold text-gray-400 uppercase mb-1">{{ __('admin.forms_ui.button_text') }}</label>
                    <input type="text" name="translations[{{ $primaryLocale }}][submit_button_text]" value="Enviar Solicitud" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block font-bold text-gray-400 uppercase mb-1">{{ __('admin.forms_ui.success_message') }}</label>
                    <input type="text" name="translations[{{ $primaryLocale }}][success_message]" value="¡Gracias por contactarnos! Responderemos a la brevedad." class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white">
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-800">
                    <button type="button" @click="open = false" class="px-4 py-2 bg-gray-800 text-gray-400 hover:text-white rounded-lg">{{ __('admin.actions.cancel') }}</button>
                    <button type="submit" class="btn-neon">{{ __('admin.forms_ui.create_button') }}</button>
                </div>
            </form>
        </x-admin.modal>
    </div>
</div>
@endsection
