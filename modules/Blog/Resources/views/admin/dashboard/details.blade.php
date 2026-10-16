        <div class="cyber-card p-6 sm:p-7 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-pen-to-square text-[#00FF99] text-base"></i>
                        <h3 class="font-mono text-sm font-bold text-white flex items-center gap-2">
                            <span class="text-[#00FF99]">&gt;</span> {{ __('admin.dashboard.published_posts') }}
                        </h3>
                    </div>
                    <a href="{{ route('admin.blog.posts.index') }}" class="text-xs font-mono text-[#00FF99] hover:underline flex items-center gap-1">
                        <span>{{ __('admin.dashboard.view_all') }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                @if($recentPosts->isEmpty())
                    <div class="text-center py-12 text-xs font-mono text-gray-500">
                        <div class="text-2xl mb-2 opacity-50"><i class="fa-solid fa-newspaper"></i></div>
                        &gt; {{ __('admin.dashboard.no_posts') }}
                    </div>
                @else
                    <div class="divide-y divide-gray-800/80 font-mono text-xs mt-3">
                        @foreach($recentPosts as $post)
                            <div class="py-3.5 flex items-center justify-between hover:bg-gray-800/30 px-2 rounded-lg transition-colors">
                                <div class="truncate max-w-[70%]">
                                    <div class="font-bold text-white truncate hover:text-[#00FF99] transition-colors">
                                        <a href="{{ route('admin.blog.posts.edit', $post) }}">
                                            {{ $post->translations->first()?->title ?? __('admin.dashboard.untitled_post') }}
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ __('admin.dashboard.by') }} <span class="text-gray-300">{{ $post->author->name ?? 'Admin' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30">
                                        {{ $post->is_published ? __('admin.dashboard.published') : __('admin.status.draft') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-4 mt-4 border-t border-gray-800/80 flex items-center justify-between text-[11px] font-mono text-gray-400">
                <span>{{ __('admin.dashboard.total_posts') }} <strong class="text-white">{{ $stats['total_posts'] }}</strong></span>
                @if(Route::has('admin.blog.posts.create'))
                <a href="{{ route('admin.blog.posts.create') }}" class="text-[#00FF99] hover:underline font-bold">{{ __('admin.dashboard.new_post') }}</a>
                @endif
            </div>
        </div>
