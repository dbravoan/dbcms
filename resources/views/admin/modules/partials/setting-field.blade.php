@props([
    'key' => '',
    'field' => [],
    'settings' => [],
])

@php
    /**
     * Single setting field shared by the global list and the language/market
     * tabs, so both render exactly the same control per type.
     */
    $type = $field['type'] ?? 'text';
    $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
    $desc = $field['description'] ?? null;
    $placeholder = $field['placeholder'] ?? '';
    $required = (bool) ($field['required'] ?? false);
    $secret = (bool) ($field['secret'] ?? false) || $type === 'password';
    $val = $settings[$key] ?? ($field['default'] ?? null);
    $hasSavedVal = ! empty($settings[$key]);
    $multiVal = is_array($val)
        ? $val
        : (is_string($val) && trim($val) !== '' ? array_map('trim', explode(',', $val)) : []);
@endphp

<div class="pt-6 first:pt-0 space-y-2">
    <!-- Label Row -->
    <div class="flex items-center justify-between">
        <label for="field-{{ $key }}" class="font-bold text-white text-xs flex items-center gap-2">
            <span>{{ $label }}</span>
            @if($required)
                <span class="text-[9px] bg-red-500/10 text-red-400 border border-red-500/30 px-1.5 py-0.2 rounded font-bold uppercase">Requerido</span>
            @endif
            @if($secret)
                <span class="text-[9px] bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30 px-1.5 py-0.2 rounded font-bold uppercase">Cifrado AES-256</span>
            @endif
        </label>

        <span class="text-[10px] text-gray-500 font-mono">key: {{ $key }}</span>
    </div>

    <!-- Field Descriptions -->
    @if($desc)
        <p class="text-[11px] text-gray-400 leading-relaxed">{{ $desc }}</p>
    @endif

    <!-- Input Renderers by Type -->
    @if($type === 'boolean')
        <div class="pt-1">
            <label class="inline-flex items-center gap-3 cursor-pointer">
                <input type="checkbox"
                       name="{{ $key }}"
                       id="field-{{ $key }}"
                       value="1"
                       {{ $val ? 'checked' : '' }}
                       class="w-4 h-4 rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99] focus:ring-0 focus:ring-offset-0 focus:border-[#00FF99] cursor-pointer">
                <span class="text-xs text-gray-300 select-none">Habilitar esta opción</span>
            </label>
        </div>

    @elseif($type === 'select')
        <select name="{{ $key }}"
                id="field-{{ $key }}"
                class="w-full bg-[#1A1A1A] border border-gray-700 hover:border-gray-600 focus:border-[#00FF99] rounded-lg p-2.5 text-white font-mono text-xs transition-colors focus:outline-none">
            @foreach(($field['options'] ?? []) as $optKey => $optLabel)
                <option value="{{ $optKey }}" {{ (string)$val === (string)$optKey ? 'selected' : '' }}>
                    {{ $optLabel }}
                </option>
            @endforeach
        </select>

    @elseif($type === 'roles_multiselect')
        <x-admin.role-multiselect
            :name="$key"
            :selected="$val"
            :placeholder="$placeholder ?: 'Seleccionar roles...'"
        />

    @elseif($type === 'entities_multiselect')
        <x-admin.entity-multiselect
            :name="$key"
            :selected="$val"
            :placeholder="$placeholder ?: 'Seleccionar entidades...'"
        />

    @elseif($type === 'multiselect')
        <x-admin.multiselect
            :name="$key"
            :options="$field['options'] ?? []"
            :selected="$val"
            :placeholder="$placeholder ?: 'Seleccionar opciones...'"
        />

    @elseif($type === 'market_select')
        <x-admin.market-select
            :name="$key"
            :value="$val"
            :placeholder="$placeholder ?: 'Seleccionar mercado...'"
        />

    @elseif($type === 'market_multiselect')
        <x-admin.market-select
            :name="$key"
            :values="$multiVal"
            multiple
            :placeholder="$placeholder ?: 'Seleccionar mercados...'"
        />

    @elseif($type === 'language_select')
        <x-admin.language-select
            :name="$key"
            :value="$val"
            :placeholder="$placeholder ?: 'Seleccionar idioma...'"
        />

    @elseif($type === 'language_multiselect')
        <x-admin.language-select
            :name="$key"
            :values="$multiVal"
            multiple
            :placeholder="$placeholder ?: 'Seleccionar idiomas...'"
        />

    @elseif($type === 'entity_select')
        <x-admin.search-select
            :source="$field['source'] ?? 'pages'"
            :name="$key"
            :value="$val"
            :placeholder="$placeholder ?: 'Buscar...'"
        />

    @elseif($type === 'entity_multiselect')
        <x-admin.search-select
            :source="$field['source'] ?? 'pages'"
            :name="$key"
            :values="$multiVal"
            multiple
            :placeholder="$placeholder ?: 'Buscar...'"
        />

    @elseif($type === 'textarea')
        <textarea name="{{ $key }}"
                  id="field-{{ $key }}"
                  rows="4"
                  placeholder="{{ $placeholder }}"
                  class="w-full bg-[#1A1A1A] border border-gray-700 hover:border-gray-600 focus:border-[#00FF99] rounded-lg p-3 text-white font-mono text-xs transition-colors focus:outline-none placeholder-gray-600 leading-relaxed">{{ $val }}</textarea>

    @elseif($secret)
        <!-- Password / Secret Input with Visibility Toggle -->
        <div x-data="{ show: false }" class="space-y-1">
            <div class="relative flex items-center">
                <input :type="show ? 'text' : 'password'"
                       name="{{ $key }}"
                       id="field-{{ $key }}"
                       placeholder="{{ $hasSavedVal ? '•••••••••••••••• (Clave guardada - Deja vacío para mantener)' : $placeholder }}"
                       autocomplete="new-password"
                       class="w-full bg-[#1A1A1A] border border-gray-700 hover:border-gray-600 focus:border-[#00FF99] rounded-lg px-3 py-2 pr-20 text-white font-mono text-xs transition-colors focus:outline-none placeholder-gray-500">

                <button type="button"
                        @click="show = !show"
                        class="absolute right-2 px-2.5 py-1 bg-[#252525] hover:bg-gray-700 border border-gray-600 rounded text-[10px] text-gray-300 hover:text-white transition-colors">
                    <span class="inline-flex items-center gap-1">
                        <i :class="show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                        <span x-text="show ? 'Ocultar' : 'Revelar'"></span>
                    </span>
                </button>
            </div>

            @if($hasSavedVal)
                <p class="text-[10px] text-[#00FF99] flex items-center gap-1.5 pt-0.5">
                    <i class="fa-solid fa-check"></i>
                    <span>Clave almacenada de forma segura y cifrada. Si dejas el campo en blanco, se conservará el valor existente.</span>
                </p>
            @endif
        </div>

    @elseif($type === 'datetime-local')
        <input type="datetime-local"
               name="{{ $key }}"
               id="field-{{ $key }}"
               value="{{ $val }}"
               class="w-full bg-[#1A1A1A] border border-gray-700 hover:border-gray-600 focus:border-[#00FF99] rounded-lg px-3 py-2.5 text-white font-mono text-xs transition-colors focus:outline-none">

    @elseif($type === 'color')
        <div class="flex items-center gap-3">
            <input type="color"
                   name="{{ $key }}"
                   id="field-{{ $key }}"
                   value="{{ $val ?: '#00FF99' }}"
                   oninput="document.getElementById('label-{{ $key }}').textContent = this.value"
                   class="w-10 h-10 rounded border border-gray-700 bg-[#1A1A1A] cursor-pointer p-0.5">
            <span class="text-xs text-gray-300 font-mono" id="label-{{ $key }}">{{ $val ?: '#00FF99' }}</span>
        </div>

    @else
        <!-- Standard Single-Line Input -->
        <input type="{{ $type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text')) }}"
               name="{{ $key }}"
               id="field-{{ $key }}"
               value="{{ $val }}"
               placeholder="{{ $placeholder }}"
               class="w-full bg-[#1A1A1A] border border-gray-700 hover:border-gray-600 focus:border-[#00FF99] rounded-lg px-3 py-2.5 text-white font-mono text-xs transition-colors focus:outline-none placeholder-gray-600">
    @endif

    @error($key)
        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
    @enderror
</div>
