            <a href="{{ route('admin.forms.index') }}" class="cyber-card p-5 hover:border-teal-300 hover:shadow-md transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-teal-50 border border-teal-200 group-hover:border-teal-400 transition-colors flex items-center justify-center text-[#0090A8] text-base"><i class="fa-solid fa-inbox"></i></span>
                    <span class="text-slate-400 group-hover:text-[#0090A8] text-xs transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm group-hover:text-[#0090A8] transition-colors">{{ __('admin.dashboard.manage_forms') }}</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ __('admin.dashboard.manage_forms_desc') }}</p>
            </a>
