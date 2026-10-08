<div class="relative w-full max-w-4xl mx-auto overflow-hidden rounded-3xl shadow-xl select-none group" x-data="{ sliderPos: 50 }" @mousemove="if($event.buttons === 1) sliderPos = Math.max(0, Math.min(100, ($event.offsetX / $el.offsetWidth) * 100))" @touchmove="sliderPos = Math.max(0, Math.min(100, (($event.touches[0].clientX - $el.getBoundingClientRect().left) / $el.offsetWidth) * 100))">
    {{-- After Image (Full Background) --}}
    <img src="{{ $afterImage }}" alt="{{ $afterLabel }}" class="w-full h-80 md:h-[450px] object-cover pointer-events-none">
    <span class="absolute top-4 right-4 bg-slate-900/80 backdrop-blur-sm text-white text-xs font-bold px-3 py-1.5 rounded-full z-10">{{ $afterLabel }}</span>

    {{-- Before Image (Clipped Overlay) --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none" :style="'width: ' + sliderPos + '%'">
        <img src="{{ $beforeImage }}" alt="{{ $beforeLabel }}" class="absolute inset-0 w-full h-80 md:h-[450px] object-cover max-w-none" style="width: 100%; min-width: 100%;">
        <span class="absolute top-4 left-4 bg-indigo-600/90 backdrop-blur-sm text-white text-xs font-bold px-3 py-1.5 rounded-full z-10">{{ $beforeLabel }}</span>
    </div>

    {{-- Handle Bar & Range Input --}}
    <div class="absolute inset-y-0 pointer-events-none" :style="'left: ' + sliderPos + '%'">
        <div class="absolute inset-y-0 -ml-0.5 w-1 bg-white shadow-2xl"></div>
        <div class="absolute top-1/2 -mt-5 -ml-5 w-10 h-10 rounded-full bg-white shadow-lg border-2 border-indigo-600 flex items-center justify-center text-indigo-600 text-xs font-black cursor-ew-resize pointer-events-auto">
            &harr;
        </div>
    </div>
    <input type="range" min="0" max="100" x-model="sliderPos" class="absolute inset-0 opacity-0 cursor-ew-resize w-full h-full">
</div>
