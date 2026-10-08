<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
    @if($gallery && $gallery->items->isNotEmpty())
        @foreach($gallery->items as $item)
            <div class="group relative overflow-hidden rounded-2xl bg-slate-100 shadow-sm aspect-w-4 aspect-h-3">
                <img src="{{ $item->image_url }}" alt="{{ $item->alt_text ?? $item->title ?? 'Gallery image' }}" class="h-64 w-full object-cover group-hover:scale-105 transition-transform duration-300">
                @if($item->title || $item->caption)
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-4">
                        <div class="text-white">
                            <div class="font-bold text-sm">{{ $item->title }}</div>
                            <div class="text-xs text-slate-200">{{ $item->caption }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="col-span-3 text-center py-8 text-slate-400">Gallery is empty or not found.</div>
    @endif
</div>
