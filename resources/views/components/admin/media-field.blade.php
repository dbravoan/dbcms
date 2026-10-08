@props([
    'name',
    'field',
    'label',
    'hint' => null,
])

{{-- Media picker bound to the surrounding Alpine `media[field]` state and the global filemanager modal. --}}
<div class="space-y-1.5">
    <label class="font-bold text-white text-xs" for="{{ $name }}">{{ $label }}</label>

    <div class="flex items-start gap-3">
        <div class="w-16 h-16 rounded-lg bg-[#1A1A1A] border border-gray-700 flex items-center justify-center overflow-hidden shrink-0">
            <template x-if="media['{{ $field }}']">
                <img :src="media['{{ $field }}']" alt="{{ $label }}" class="w-full h-full object-contain">
            </template>
            <template x-if="! media['{{ $field }}']">
                <i class="fa-solid fa-image text-gray-600 text-lg"></i>
            </template>
        </div>

        <div class="flex-1 min-w-0 space-y-2">
            <input type="text"
                   id="{{ $name }}"
                   name="{{ $name }}"
                   x-model="media['{{ $field }}']"
                   class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors"
                   placeholder="/images/logo.svg o URL">

            <div class="flex items-center gap-2">
                <button type="button"
                        @click="openMedia('{{ $field }}')"
                        class="px-3 py-1.5 bg-[#00FF99]/10 hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/40 rounded-lg text-[11px] font-bold transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-folder-open"></i>
                    <span>{{ __('admin.media.choose') }}</span>
                </button>

                <button type="button"
                        x-show="media['{{ $field }}']"
                        @click="clearMedia('{{ $field }}')"
                        class="px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-400 hover:text-red-400 rounded-lg text-[11px] font-bold transition-colors">
                    Quitar
                </button>
            </div>

            @if($hint)
                <p class="text-[11px] text-gray-500">{{ $hint }}</p>
            @endif
        </div>
    </div>
</div>
