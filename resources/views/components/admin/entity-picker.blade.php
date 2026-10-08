@props([
    'items' => [],
    'placeholder' => 'Buscar página, artículo o categoría...',
    'onSelect' => 'onSelectEntity($item)',
])

<div x-data="{
        open: false,
        search: '',
        items: {{ json_encode($items) }},
        get filtered() {
            if (!this.search.trim()) return this.items.slice(0, 30);
            const q = this.search.toLowerCase().trim();
            return this.items.filter(i =>
                (i.label && i.label.toLowerCase().includes(q)) ||
                (i.url && i.url.toLowerCase().includes(q)) ||
                (i.type && i.type.toLowerCase().includes(q))
            ).slice(0, 30);
        },
        select(item) {
            {{ $onSelect }};
            this.open = false;
            this.search = '';
        }
     }"
     class="relative font-mono text-xs"
     @click.outside="open = false"
     @keydown.escape.window="open = false">

    <div class="relative">
        <input type="text"
               x-model="search"
               @focus="open = true"
               @input="open = true"
               placeholder="{{ $placeholder }}"
               class="w-full bg-[#181818] border border-gray-700 hover:border-[#00FF99]/60 focus:border-[#00FF99] rounded-lg pl-8 pr-3 py-2 text-white text-xs placeholder-gray-500 transition-all">
        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-3 text-gray-500 text-xs"></i>
    </div>

    <div x-show="open"
         x-cloak
         x-transition
         class="absolute z-50 left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-xl bg-[#181818] border border-gray-700 shadow-2xl divide-y divide-gray-800/80">

        <template x-for="item in filtered" :key="item.type + '-' + (item.id || item.url)">
            <div @click="select(item)"
                 class="p-2.5 hover:bg-[#252525] cursor-pointer flex items-center justify-between gap-3 transition-colors group">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-6 h-6 rounded bg-black/50 border border-gray-800 flex items-center justify-center text-xs text-[#00FF99] shrink-0">
                        <i :class="item.icon || 'fa-solid fa-link'"></i>
                    </span>
                    <div class="truncate">
                        <span class="font-bold text-white text-xs group-hover:text-[#00FF99] transition-colors truncate block" x-text="item.label"></span>
                        <span class="text-[10px] text-gray-500 font-mono truncate block" x-text="item.url"></span>
                    </div>
                </div>
                <span class="text-[9px] px-1.5 py-0.5 rounded bg-black/60 border border-gray-800 text-gray-400 font-mono uppercase shrink-0" x-text="item.badge"></span>
            </div>
        </template>

        <div x-show="filtered.length === 0" class="p-4 text-center text-gray-500 text-xs">
            No se encontraron contenidos para "<span x-text="search"></span>"
        </div>
    </div>
</div>
