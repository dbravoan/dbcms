<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @foreach($posts as $post)
        @php $t = $post->translations->where('locale', app()->getLocale())->first() ?? $post->translations->first(); @endphp
        <div class="bg-white dark:bg-[#181818] rounded-2xl shadow-sm border border-slate-100 dark:border-gray-800 p-6 flex flex-col justify-between hover:border-indigo-300 dark:hover:border-[#00FF99]/60 transition-all hover:-translate-y-0.5 group">
            <div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:text-[#00FF99] bg-indigo-50 dark:bg-[#00FF99]/10 rounded-full mb-3 border border-indigo-100 dark:border-[#00FF99]/20">
                    <i class="fa-solid fa-star text-amber-500 dark:text-[#00FF99] text-[10px]"></i>
                    Featured Article
                </span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2 group-hover:text-indigo-600 dark:group-hover:text-[#00FF99] transition-colors">
                    <a href="{{ url(($currentMarket->code ?? 'es') . '/' . app()->getLocale() . '/blog/' . ($t->slug ?? $post->id)) }}">{{ $t->title ?? 'Post' }}</a>
                </h3>
                <p class="text-slate-500 dark:text-gray-400 text-sm line-clamp-2">{{ $t->summary ?? '' }}</p>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-gray-800 flex items-center justify-between text-xs text-slate-400 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <i class="fa-regular fa-clock"></i>
                    {{ $post->reading_time_minutes ?? 3 }} min read
                </span>
                <a href="{{ url(($currentMarket->code ?? 'es') . '/' . app()->getLocale() . '/blog/' . ($t->slug ?? $post->id)) }}" class="font-semibold text-indigo-600 dark:text-[#00FF99] flex items-center gap-1 hover:underline">
                    <span>Read</span>
                    <i class="fa-solid fa-arrow-right text-[10px] transition-transform group-hover:translate-x-1"></i>
                </a>
            </div>
        </div>
    @endforeach
</div>
