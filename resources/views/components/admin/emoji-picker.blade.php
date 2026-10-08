@props([
    'name' => 'flag',
    'value' => '',
    'placeholder' => null,
    // Bandera gobernada por un estado de Alpine (formulario de edición): se pasa
    // como expresión y el componente la sigue mientras el modal esté abierto.
    'bind' => null,
])

@php
    $globe = \App\Core\Localization\FlagCatalog::GLOBE;
    $options = \App\Core\Localization\FlagCatalog::options(app()->getLocale());
    $current = trim((string) $value) !== '' ? (string) $value : $globe;
    $inputPlaceholder = $placeholder ?? __('admin.markets.emoji_placeholder');
@endphp

{{-- Flag / emoji picker: a searchable grid (with the neutral globe) plus a free
     emoji input. Works for markets (country, group or international) and
     languages. Extra attributes (e.g. `x-init`) pass through to the root. --}}
<div
    x-data="{
        open: false,
        query: '',
        value: @js($current),
        options: @js($options),
        syncFrom(external) {
            if (typeof external === 'string' || typeof external === 'number') {
                this.value = String(external) || @js($globe);
            }
        },
        pick(emoji) { this.value = emoji; this.open = false; },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (! q) return this.options;
            return this.options.filter((option) =>
                option.label.toLowerCase().includes(q) || (option.code || '').toLowerCase().includes(q)
            );
        },
    }"
    @if($bind !== null) x-init="$watch(() => { try { return {{ $bind }}; } catch (e) { return null; } }, (v) => syncFrom(v), { immediate: true })" @endif
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <input type="hidden" name="{{ $name }}" :value="value">

    <div class="flex items-center gap-2">
        <button type="button"
                @click="open = !open"
                class="w-12 h-10 shrink-0 bg-[#1A1A1A] border border-gray-700 hover:border-[#00FF99] rounded-lg text-lg leading-none flex items-center justify-center transition-colors"
                aria-label="{{ __('admin.markets.select_emoji') }}">
            <span x-text="value || '{{ $globe }}'"></span>
        </button>
        <input type="text"
               x-model="value"
               maxlength="16"
               placeholder="{{ $inputPlaceholder }}"
               class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-center text-lg">
    </div>

    <div x-show="open"
         style="display:none"
         @click.away="open = false"
         class="absolute z-50 mt-2 left-0 w-72 bg-[#0F0F0F] border border-gray-700 rounded-xl shadow-2xl p-3 space-y-2">
        <input type="text"
               x-model="query"
               placeholder="{{ __('admin.markets.search_emoji') }}"
               class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-1.5 text-white text-xs">

        <div class="grid grid-cols-7 gap-1 max-h-56 overflow-y-auto">
            <button type="button"
                    @click="pick('{{ $globe }}')"
                    title="{{ __('admin.markets.flag_international') }}"
                    class="h-8 rounded hover:bg-[#222] text-lg leading-none">🌐</button>
            <template x-for="option in filtered" :key="option.value + option.code">
                <button type="button"
                        @click="pick(option.value)"
                        :title="option.label + (option.code ? ' (' + option.code + ')' : '')"
                        class="h-8 rounded hover:bg-[#222] text-lg leading-none"
                        x-text="option.value"></button>
            </template>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-gray-800">
            <span class="text-[10px] text-gray-500" x-text="filtered.length + ' emojis'"></span>
            <button type="button" @click="pick('')" class="text-[10px] text-gray-400 hover:text-white">
                {{ __('admin.markets.clear_emoji') }}
            </button>
        </div>
    </div>
</div>
