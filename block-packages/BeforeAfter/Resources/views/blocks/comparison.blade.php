<div class="space-y-4 w-full max-w-4xl mx-auto">
    @if ($title !== '')
        <h2 class="text-center text-2xl font-bold cms-text">{{ $title }}</h2>
    @endif
    <div class="relative w-full h-80 sm:h-96 overflow-hidden rounded-2xl border cms-border select-none shadow-xl focus-within:ring-2 focus-within:ring-[var(--cms-accent)]"
         x-data="{ position: 50 }">
        <img src="{{ $afterImage }}" alt="{{ $afterLabel }}" class="absolute inset-0 w-full h-full object-cover pointer-events-none">
        <span class="absolute top-4 right-4 z-10 px-3 py-1 rounded-full bg-black/80 text-white text-xs">{{ $afterLabel }}</span>

        <div class="absolute inset-0 pointer-events-none" :style="'clip-path: inset(0 ' + (100 - position) + '% 0 0)'">
            <img src="{{ $beforeImage }}" alt="{{ $beforeLabel }}" class="absolute inset-0 w-full h-full object-cover">
            <span class="absolute top-4 left-4 px-3 py-1 rounded-full bg-black/80 text-white text-xs">{{ $beforeLabel }}</span>
        </div>

        <div class="absolute inset-y-0 w-1 cms-accent-bg pointer-events-none" :style="'left: ' + position + '%'">
            <span class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 w-10 h-10 rounded-full cms-surface-2 border-2 cms-border-accent flex items-center justify-center cms-accent" aria-hidden="true">&harr;</span>
        </div>
        <input type="range" min="0" max="100" step="1" value="50" x-model.number="position"
               aria-label="{{ $beforeLabel }} / {{ $afterLabel }}"
               class="absolute inset-0 z-20 opacity-0 cursor-ew-resize w-full h-full">
    </div>
</div>
