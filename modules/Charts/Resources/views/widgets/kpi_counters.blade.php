<div class="grid grid-cols-2 md:grid-cols-4 gap-8">
    @foreach($stats as $stat)
        <div class="bg-white/80 backdrop-blur-md p-6 rounded-3xl border border-slate-100 shadow-sm text-center transform hover:-translate-y-1 transition-transform">
            <div class="text-3xl sm:text-4xl font-extrabold text-indigo-600 mb-1 tracking-tight">{{ $stat['value'] }}</div>
            <div class="text-sm font-bold text-slate-800 mb-1">{{ $stat['label'] }}</div>
            @if(isset($stat['description']))
                <div class="text-xs text-slate-400">{{ $stat['description'] }}</div>
            @endif
        </div>
    @endforeach
</div>
