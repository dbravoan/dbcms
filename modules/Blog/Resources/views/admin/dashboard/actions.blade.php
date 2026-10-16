            <a href="{{ route('admin.blog.posts.create') }}" class="cyber-card p-5 hover:border-[#00FF99]/50 hover:bg-[#1A1A1A] transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-[#1F1F1F] border border-gray-800 group-hover:border-[#00FF99]/40 transition-colors flex items-center justify-center text-[#00FF99] text-base"><i class="fa-solid fa-pen-nib"></i></span>
                    <span class="text-gray-500 group-hover:text-[#00FF99] text-xs font-mono transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-white text-sm group-hover:text-[#00FF99] transition-colors">{{ __('admin.dashboard.write_post') }}</h3>
                <p class="text-xs text-gray-400 mt-1 leading-relaxed">{{ __('admin.dashboard.write_post_desc') }}</p>
            </a>
