<div class="space-y-4 font-mono text-xs">
    @if(empty($diffResult->fieldDiffs))
        <div class="p-4 bg-gray-900/50 border border-gray-800 rounded-lg text-gray-400 text-center">
            <i class="fa-solid fa-check-circle text-emerald-400 mr-1.5"></i>
            {{ __('admin.content_audit.no_changes') }}
        </div>
    @else
        <div class="border border-gray-800 rounded-lg overflow-hidden bg-black/40">
            <div class="px-4 py-2 bg-gray-900 border-b border-gray-800 flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-300 uppercase tracking-wider">
                    <i class="fa-solid fa-code-compare text-[#00FF99] mr-1.5"></i>
                    {{ __('admin.content_audit.field_changes') }} ({{ count($diffResult->fieldDiffs) }})
                </span>
                <span class="text-[10px] text-gray-500">
                    <span class="text-emerald-400 font-bold">{{ __('admin.content_audit.additions') }}</span> &bull; 
                    <span class="text-rose-400 font-bold">{{ __('admin.content_audit.removals') }}</span>
                </span>
            </div>

            <div class="divide-y divide-gray-800/80">
                @foreach($diffResult->fieldDiffs as $field => $data)
                    <div class="p-3.5 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded bg-gray-800 text-gray-200 text-[11px] font-bold">
                                {{ $field }}
                            </span>
                        </div>

                        @if(isset($diffResult->textDiffs[$field]))
                            <!-- Line-by-line diff -->
                            <div class="bg-gray-950 p-2.5 rounded border border-gray-800/80 space-y-0.5 overflow-x-auto text-[11px] leading-relaxed max-h-60 overflow-y-auto">
                                @foreach($diffResult->textDiffs[$field] as $line)
                                    @if($line['type'] === 'added')
                                        <div class="bg-emerald-500/15 text-emerald-300 px-2 py-0.5 rounded flex items-start gap-2">
                                            <span class="select-none font-bold text-emerald-400">+</span>
                                            <span class="whitespace-pre-wrap break-words">{!! htmlspecialchars($line['content']) !!}</span>
                                        </div>
                                    @elseif($line['type'] === 'removed')
                                        <div class="bg-rose-500/15 text-rose-300 px-2 py-0.5 rounded flex items-start gap-2 line-through opacity-80">
                                            <span class="select-none font-bold text-rose-400">-</span>
                                            <span class="whitespace-pre-wrap break-words">{!! htmlspecialchars($line['content']) !!}</span>
                                        </div>
                                    @else
                                        <div class="text-gray-400 px-2 py-0.5 flex items-start gap-2">
                                            <span class="select-none text-gray-600">&nbsp;</span>
                                            <span class="whitespace-pre-wrap break-words">{!! htmlspecialchars($line['content']) !!}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <!-- Field Before & After -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                                <div class="bg-rose-950/20 border border-rose-900/30 p-2.5 rounded text-rose-300">
                                    <span class="text-[9px] uppercase tracking-wider block text-rose-400 font-bold mb-1">{{ __('admin.content_audit.before') }}</span>
                                    <span class="break-words line-through">{{ is_array($data['before']) ? json_encode($data['before']) : ($data['before'] ?? __('admin.content_audit.empty')) }}</span>
                                </div>
                                <div class="bg-emerald-950/20 border border-emerald-900/30 p-2.5 rounded text-emerald-300">
                                    <span class="text-[9px] uppercase tracking-wider block text-emerald-400 font-bold mb-1">{{ __('admin.content_audit.after') }}</span>
                                    <span class="break-words font-semibold">{{ is_array($data['after']) ? json_encode($data['after']) : ($data['after'] ?? __('admin.content_audit.empty')) }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
