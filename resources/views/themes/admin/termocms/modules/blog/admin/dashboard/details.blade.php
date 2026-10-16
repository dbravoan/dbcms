        <div class="cyber-card p-6 sm:p-7 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-pen-to-square text-[#0090A8] text-base"></i>
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            {{ __('admin.dashboard.published_posts') }}
                        </h3>
                    </div>
                    <a href="{{ route('admin.blog.posts.index') }}" class="text-xs text-[#0090A8] hover:underline font-semibold flex items-center gap-1">
                        <span>{{ __('admin.dashboard.view_all') }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                @if($recentPosts->isEmpty())
                    <div class="text-center py-12 text-xs text-slate-400">
                        <div class="text-2xl mb-2 opacity-50"><i class="fa-solid fa-newspaper"></i></div>
                        {{ __('admin.dashboard.no_posts') }}
                    </div>
                @else
                    <div class="divide-y divide-slate-100 text-xs mt-3">
                        @foreach($recentPosts as $post)
                            <div class="py-3.5 flex items-center justify-between hover:bg-slate-50 px-2 rounded-lg transition-colors">
                                <div class="truncate max-w-[70%]">
                                    <div class="font-bold text-slate-800 truncate hover:text-[#0090A8] transition-colors">
                                        <a href="{{ route('admin.blog.posts.edit', $post) }}">
                                            {{ $post->translations->first()?->title ?? __('admin.dashboard.untitled_post') }}
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        {{ __('admin.dashboard.by') }} <span class="text-slate-700">{{ $post->author->name ?? 'Admin' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                        {{ $post->is_published ? __('admin.dashboard.published') : __('admin.status.draft') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>{{ __('admin.dashboard.total_posts') }} <strong class="text-slate-800">{{ $stats['total_posts'] }}</strong></span>
                @if(Route::has('admin.blog.posts.create'))
                <a href="{{ route('admin.blog.posts.create') }}" class="text-[#0090A8] hover:underline font-bold">{{ __('admin.dashboard.new_post') }}</a>
                @endif
            </div>
        </div>
