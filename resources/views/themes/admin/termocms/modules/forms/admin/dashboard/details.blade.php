        <div class="cyber-card p-6 sm:p-7 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-inbox text-[#0090A8] text-base"></i>
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            {{ __('admin.dashboard.recent_leads') }}
                        </h3>
                    </div>
                    <a href="{{ route('admin.forms.submissions') }}" class="text-xs text-[#0090A8] hover:underline font-semibold flex items-center gap-1">
                        <span>{{ __('admin.dashboard.view_all') }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                @if($recentSubmissions->isEmpty())
                    <div class="text-center py-12 text-xs text-slate-400">
                        <div class="text-2xl mb-2 opacity-50"><i class="fa-solid fa-inbox"></i></div>
                        {{ __('admin.dashboard.no_submissions') }}
                    </div>
                @else
                    <div class="divide-y divide-slate-100 text-xs mt-3">
                        @foreach($recentSubmissions as $sub)
                            <div class="py-3.5 flex items-center justify-between hover:bg-slate-50 px-2 rounded-lg transition-colors">
                                <div class="truncate max-w-[70%]">
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#0090A8]"></span>
                                        <span class="truncate">{{ $sub->form->name ?? __('admin.dashboard.contact_form') }}</span>
                                    </div>
                                    <div class="text-slate-500 text-[11px] mt-0.5 pl-3.5 truncate">
                                        {{ $sub->payload['email'] ?? $sub->payload['nombre'] ?? __('admin.dashboard.anonymous_contact') }}
                                    </div>
                                </div>
                                <span class="text-[10px] text-slate-500 shrink-0 bg-slate-100 px-2 py-1 rounded border border-slate-200">
                                    {{ $sub->created_at->diffForHumans() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>{{ __('admin.dashboard.total_received') }} <strong class="text-[#0090A8] font-bold">{{ $stats['total_submissions'] }}</strong></span>
                <a href="{{ route('admin.forms.submissions') }}" class="text-slate-600 hover:text-[#0090A8] transition-colors">{{ __('admin.dashboard.full_inbox') }} &nearr;</a>
            </div>
        </div>
