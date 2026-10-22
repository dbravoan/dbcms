            <a href="{{ route('admin.forms.submissions') }}" class="cyber-card p-5 min-w-0 hover:border-teal-300 hover:shadow-md transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-[11px] uppercase font-semibold group-hover:text-slate-700">{{ __('admin.dashboard.leads') }}</span>
                        <span class="p-1.5 rounded-lg bg-teal-50 border border-teal-200 flex items-center justify-center text-[#0090A8]"><i class="fa-solid fa-inbox text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-extrabold text-[#0090A8]">{{ $stats['total_submissions'] }}</div>
                </div>
                <div class="text-[10px] text-[#0090A8] mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between font-semibold">
                    <span>{{ __('admin.dashboard.leads_hint') }}</span>
                    <span class="text-slate-400 group-hover:text-[#0090A8]">&rarr;</span>
                </div>
            </a>
