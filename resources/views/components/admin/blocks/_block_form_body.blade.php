@props(['blockKey' => 'block', 'dynamicPayloads' => [], 'isSubBlock' => false, 'colIdx' => null, 'subIdx' => null])
@php($b = $blockKey)
<div class="space-y-4">
    <template x-if="!isBlockAvailable({{ $b }})">
        <div role="status" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
            <strong>Bloque no disponible</strong>
            <p>Su contenido permanece guardado. Reactiva su paquete para editarlo y mostrarlo.</p>
            <span class="font-mono text-xs" x-text="{{ $b }}.type"></span>
        </div>
    </template>
    <template x-if="isBlockAvailable({{ $b }}) && !typeDef({{ $b }}.type).editorView">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="field in schemaFields({{ $b }})" :key="field.key">
                <label class="block space-y-1" :class="['textarea', 'json', 'array', 'list', 'repeater'].includes(field.type) ? 'md:col-span-2' : ''">
                    <span class="block text-xs font-semibold" x-text="field.label || field.key"></span>
                    <template x-if="['text', 'string', 'url', 'email'].includes(field.type || 'text')">
                        <input :type="['url', 'email'].includes(field.type) ? field.type : 'text'" :required="field.required === true" x-model="{{ $b }}[field.key]" @input="syncBlocksToLocale()" class="w-full rounded-lg border cms-border cms-surface px-3 py-2 text-sm">
                    </template>
                    <template x-if="field.type === 'textarea'">
                        <textarea :required="field.required === true" x-model="{{ $b }}[field.key]" @input="syncBlocksToLocale()" rows="5" class="w-full rounded-lg border cms-border cms-surface px-3 py-2 text-sm"></textarea>
                    </template>
                    <template x-if="['number', 'integer'].includes(field.type)">
                        <input type="number" :step="field.type === 'integer' ? 1 : 'any'" :required="field.required === true" x-model.number="{{ $b }}[field.key]" @input="syncBlocksToLocale()" class="w-full rounded-lg border cms-border cms-surface px-3 py-2 text-sm">
                    </template>
                    <template x-if="field.type === 'boolean'">
                        <input type="checkbox" x-model="{{ $b }}[field.key]" @change="syncBlocksToLocale()" class="rounded border cms-border">
                    </template>
                    <template x-if="field.type === 'select'">
                        <select :required="field.required === true" x-model="{{ $b }}[field.key]" @change="syncBlocksToLocale()" class="w-full rounded-lg border cms-border cms-surface px-3 py-2 text-sm">
                            <template x-for="(label, key) in (field.options || {})" :key="key"><option :value="Array.isArray(field.options) ? label : key" x-text="label"></option></template>
                        </select>
                    </template>
                    <template x-if="['json', 'array'].includes(field.type)">
                        <textarea :value="JSON.stringify({{ $b }}[field.key] || [], null, 2)" @change="updateJsonField({{ $b }}, field.key, $event.target)" rows="7" spellcheck="false" class="w-full rounded-lg border cms-border cms-surface px-3 py-2 text-sm font-mono"></textarea>
                    </template>
                    <template x-if="field.type === 'list'">
                        <textarea :value="({{ $b }}[field.key] || []).join('\n')" @change="{{ $b }}[field.key] = $event.target.value.split('\n').filter(value => value.trim()); syncBlocksToLocale()" rows="4" class="w-full rounded-lg border cms-border cms-surface px-3 py-2 text-sm" placeholder="Un elemento por línea"></textarea>
                    </template>
                    <template x-if="field.type === 'repeater'">
                        <div class="space-y-3">
                            <template x-for="(item, itemIndex) in ({{ $b }}[field.key] || [])" :key="itemIndex">
                                <div class="rounded-lg border cms-border p-3 space-y-2">
                                    <template x-for="(child, childKey) in field.fields" :key="childKey">
                                        <label class="block space-y-1">
                                            <span class="block text-xs" x-text="child.label || childKey"></span>
                                            <template x-if="child.type === 'list'"><textarea :value="(item[childKey] || []).join('\n')" @change="item[childKey] = $event.target.value.split('\n').filter(value => value.trim()); syncBlocksToLocale()" rows="3" class="w-full rounded border cms-border cms-surface p-2 text-sm" placeholder="Un elemento por línea"></textarea></template>
                                            <template x-if="child.type === 'textarea'"><textarea :required="child.required === true" x-model="item[childKey]" @input="syncBlocksToLocale()" rows="3" class="w-full rounded border cms-border cms-surface p-2 text-sm"></textarea></template>
                                            <template x-if="!['list', 'textarea'].includes(child.type)"><input :type="['number', 'integer'].includes(child.type) ? 'number' : 'text'" :required="child.required === true" x-model="item[childKey]" @input="syncBlocksToLocale()" class="w-full rounded border cms-border cms-surface p-2 text-sm"></template>
                                        </label>
                                    </template>
                                    <x-admin.button type="button" variant="ghost" size="sm" @click="{{ $b }}[field.key].splice(itemIndex, 1); syncBlocksToLocale()">Eliminar elemento</x-admin.button>
                                </div>
                            </template>
                            <x-admin.button type="button" variant="secondary" size="sm" @click="addSchemaItem({{ $b }}, field)">Añadir elemento</x-admin.button>
                        </div>
                    </template>
                    <span x-show="field.description" x-text="field.description" class="block text-xs opacity-70"></span>
                </label>
            </template>
        </div>
    </template>
    @foreach($activeBlockTypes as $definition)
        @if($definition->getEditorView())
            <template x-if="{{ $b }}.type === @js($definition->getType())">
                <div>@include($definition->getEditorView(), ['blockKey' => $b])</div>
            </template>
        @endif
    @endforeach
</div>
