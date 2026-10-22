@props(['blockKey' => 'block', 'dynamicPayloads' => [], 'isSubBlock' => false, 'colIdx' => null, 'subIdx' => null])
@php($b = $blockKey)
<div class="admin-block-fields space-y-4">
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
                <div class="admin-field" :class="['textarea', 'json', 'array', 'list', 'repeater'].includes(field.type) ? 'md:col-span-2' : ''">
                    <label x-show="field.type !== 'repeater'" :for="'block-field-' + {{ $b }}.id + '-' + field.key" class="admin-field-label" x-text="field.label || field.key"></label>
                    <span x-show="field.type === 'repeater'" :id="'block-field-' + {{ $b }}.id + '-' + field.key + '-label'" class="admin-field-label" x-text="field.label || field.key"></span>
                    <template x-if="['text', 'string', 'url', 'email'].includes(field.type || 'text')">
                        <input :id="'block-field-' + {{ $b }}.id + '-' + field.key" :aria-describedby="field.description ? 'block-field-' + {{ $b }}.id + '-' + field.key + '-hint' : null" :type="field.type === 'email' ? 'email' : 'text'" :inputmode="field.type === 'url' ? 'url' : null" :required="field.required === true" x-model="{{ $b }}[field.key]" @input="syncBlocksToLocale()" class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm">
                    </template>
                    <template x-if="field.type === 'textarea'">
                        <textarea :id="'block-field-' + {{ $b }}.id + '-' + field.key" :aria-describedby="field.description ? 'block-field-' + {{ $b }}.id + '-' + field.key + '-hint' : null" :required="field.required === true" x-model="{{ $b }}[field.key]" @input="syncBlocksToLocale()" rows="5" class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm"></textarea>
                    </template>
                    <template x-if="['number', 'integer'].includes(field.type)">
                        <input :id="'block-field-' + {{ $b }}.id + '-' + field.key" :aria-describedby="field.description ? 'block-field-' + {{ $b }}.id + '-' + field.key + '-hint' : null" type="number" :step="field.type === 'integer' ? 1 : 'any'" :required="field.required === true" x-model.number="{{ $b }}[field.key]" @input="syncBlocksToLocale()" class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm">
                    </template>
                    <template x-if="field.type === 'boolean'">
                        <input :id="'block-field-' + {{ $b }}.id + '-' + field.key" :aria-describedby="field.description ? 'block-field-' + {{ $b }}.id + '-' + field.key + '-hint' : null" type="checkbox" x-model="{{ $b }}[field.key]" @change="syncBlocksToLocale()" class="rounded border border-[color:var(--admin-border)]">
                    </template>
                    <template x-if="field.type === 'select'">
                        <select :id="'block-field-' + {{ $b }}.id + '-' + field.key" :aria-describedby="field.description ? 'block-field-' + {{ $b }}.id + '-' + field.key + '-hint' : null" :required="field.required === true" x-model="{{ $b }}[field.key]" @change="syncBlocksToLocale()" class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm">
                            <template x-for="(label, key) in (field.options || {})" :key="key"><option :value="Array.isArray(field.options) ? label : key" x-text="label"></option></template>
                        </select>
                    </template>
                    <template x-if="['json', 'array'].includes(field.type)">
                        <textarea :id="'block-field-' + {{ $b }}.id + '-' + field.key" :aria-describedby="field.description ? 'block-field-' + {{ $b }}.id + '-' + field.key + '-hint' : null" :value="JSON.stringify({{ $b }}[field.key] || [], null, 2)" @change="updateJsonField({{ $b }}, field.key, $event.target)" rows="7" spellcheck="false" class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm font-mono"></textarea>
                    </template>
                    <template x-if="field.type === 'list'">
                        <textarea :id="'block-field-' + {{ $b }}.id + '-' + field.key" :aria-describedby="field.description ? 'block-field-' + {{ $b }}.id + '-' + field.key + '-hint' : null" :value="({{ $b }}[field.key] || []).join('\n')" @change="{{ $b }}[field.key] = $event.target.value.split('\n').filter(value => value.trim()); syncBlocksToLocale()" rows="4" class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm" placeholder="Un elemento por línea"></textarea>
                    </template>
                    <template x-if="field.type === 'repeater'">
                        <div class="space-y-3" role="group" :aria-labelledby="'block-field-' + {{ $b }}.id + '-' + field.key + '-label'">
                            <template x-for="(item, itemIndex) in ({{ $b }}[field.key] || [])" :key="itemIndex">
                                <div class="admin-block-repeater-item rounded-lg border border-[color:var(--admin-border)] p-3 space-y-2">
                                    <template x-for="(child, childKey) in field.fields" :key="childKey">
                                        <div class="admin-field">
                                            <label :for="'block-field-' + {{ $b }}.id + '-' + field.key + '-' + itemIndex + '-' + childKey" class="admin-field-label" x-text="child.label || childKey"></label>
                                            <template x-if="child.type === 'list'"><textarea :id="'block-field-' + {{ $b }}.id + '-' + field.key + '-' + itemIndex + '-' + childKey" :value="(item[childKey] || []).join('\n')" @change="item[childKey] = $event.target.value.split('\n').filter(value => value.trim()); syncBlocksToLocale()" rows="3" class="admin-control font-mono" placeholder="Un elemento por línea"></textarea></template>
                                            <template x-if="child.type === 'textarea'"><textarea :id="'block-field-' + {{ $b }}.id + '-' + field.key + '-' + itemIndex + '-' + childKey" :required="child.required === true" x-model="item[childKey]" @input="syncBlocksToLocale()" rows="3" class="admin-control"></textarea></template>
                                            <template x-if="!['list', 'textarea'].includes(child.type)"><input :id="'block-field-' + {{ $b }}.id + '-' + field.key + '-' + itemIndex + '-' + childKey" :type="['number', 'integer'].includes(child.type) ? 'number' : 'text'" :required="child.required === true" x-model="item[childKey]" @input="syncBlocksToLocale()" class="admin-control"></template>
                                        </div>
                                    </template>
                                    <x-admin.button type="button" variant="ghost" size="sm" @click="{{ $b }}[field.key].splice(itemIndex, 1); syncBlocksToLocale()">Eliminar elemento</x-admin.button>
                                </div>
                            </template>
                            <x-admin.button type="button" variant="secondary" size="sm" @click="addSchemaItem({{ $b }}, field)">Añadir elemento</x-admin.button>
                        </div>
                    </template>
                    <span :id="'block-field-' + {{ $b }}.id + '-' + field.key + '-hint'" x-show="field.description" x-text="field.description" class="admin-field-hint"></span>
                </div>
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
