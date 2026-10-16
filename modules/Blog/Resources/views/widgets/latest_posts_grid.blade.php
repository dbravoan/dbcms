@php($gridClass = match ((string) ($parameters['grid_columns'] ?? '3')) { '2' => 'md:grid-cols-2', '4' => 'md:grid-cols-4', default => 'md:grid-cols-3' })
<div class="grid grid-cols-1 {{ $gridClass }} gap-8">
    @foreach($posts as $post)
        @php $t = $post->translations->where('locale', app()->getLocale())->first() ?? $post->translations->first(); @endphp
        <div class="bg-white dark:bg-[#181818] rounded-2xl shadow-sm border border-slate-100 dark:border-gray-800 overflow-hidden hover:shadow-md dark:hover:border-[#00FF99]/60 transition-all hover:-translate-y-0.5 group flex flex-col justify-between">
            <div>
                @if($post->featured_image)
                    <div class="h-44 w-full overflow-hidden relative">
                        <img src="{{ $post->featured_image }}" alt="{{ $t->title ?? '' }}" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold text-white bg-black/70 backdrop-blur-sm rounded-md border border-white/10">
                            <i class="fa-solid fa-folder text-indigo-400 dark:text-[#00FF99]"></i>
                            {{ $post->category->translations->first()->name ?? 'General' }}
                        </span>
                    </div>
                @endif
                <div class="p-6">
                    @if(!$post->featured_image)
                        <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-[#00FF99] mb-2">
                            <i class="fa-solid fa-folder text-[10px]"></i>
                            {{ $post->category->translations->first()->name ?? 'General' }}
                        </div>
                    @endif
                    <h4 class="font-bold text-slate-900 dark:text-white mb-2 group-hover:text-indigo-600 dark:group-hover:text-[#00FF99] transition-colors text-base">
                        <a href="{{ url(($currentMarket->code ?? 'es') . '/' . app()->getLocale() . '/blog/' . ($t->slug ?? $post->id)) }}">{{ $t->title ?? 'Post' }}</a>
                    </h4>
                    <p class="text-slate-500 dark:text-gray-400 text-xs line-clamp-2 leading-relaxed">{{ $t->summary ?? '' }}</p>
                </div>
            </div>
            <div class="px-6 pb-6 pt-3 border-t border-slate-50 dark:border-gray-800 flex items-center justify-between text-xs text-slate-400 dark:text-gray-400">
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
