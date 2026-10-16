            <a href="{{ route('admin.forms.submissions') }}" class="cyber-card p-5 flex-1 min-w-[170px] sm:min-w-[200px] hover:border-[#00FF99]/40 hover:shadow-[0_0_20px_rgba(0,255,153,0.12)] transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-gray-400 mb-2">
                        <span class="text-[11px] font-mono uppercase font-semibold group-hover:text-gray-300">{{ __('admin.dashboard.leads') }}</span>
                        <span class="p-1.5 rounded bg-[#1A1A1A] border border-gray-800 flex items-center justify-center text-[#00FF99]"><i class="fa-solid fa-inbox text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-[#00FF99] font-mono">{{ $stats['total_submissions'] }}</div>
                </div>
                <div class="text-[10px] text-[#00FF99] font-mono mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                    <span>{{ __('admin.dashboard.leads_hint') }}</span>
                    <span class="text-gray-500 group-hover:text-[#00FF99]">&rarr;</span>
                </div>
            </a>
