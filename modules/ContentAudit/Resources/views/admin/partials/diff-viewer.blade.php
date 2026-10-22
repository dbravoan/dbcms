<div class="space-y-4 font-mono text-xs">
    @if(empty($diffResult->fieldDiffs))
        <div class="p-4 bg-[color:var(--admin-surface-raised)]/50 border border-[color:var(--admin-border)] rounded-lg text-[color:var(--admin-muted)] text-center">
            <i class="fa-solid fa-check-circle text-emerald-400 mr-1.5"></i>
            {{ __('admin.content_audit.no_changes') }}
        </div>
    @else
        <div class="border border-[color:var(--admin-border)] rounded-lg overflow-hidden bg-[color:var(--admin-surface)]/40">
            <div class="px-4 py-2 bg-[color:var(--admin-surface-raised)] border-b border-[color:var(--admin-border)] flex items-center justify-between">
                <span class="text-[11px] font-bold text-[color:var(--admin-text)] uppercase tracking-wider">
                    <i class="fa-solid fa-code-compare text-[color:var(--admin-accent)] mr-1.5"></i>
                    {{ __('admin.content_audit.field_changes') }} ({{ count($diffResult->fieldDiffs) }})
                </span>
                <span class="text-[10px] text-[color:var(--admin-muted)]">
                    <span class="text-emerald-400 font-bold">{{ __('admin.content_audit.additions') }}</span> &bull; 
                    <span class="text-rose-400 font-bold">{{ __('admin.content_audit.removals') }}</span>
                </span>
            </div>

            <div class="divide-y divide-[color:var(--admin-border)]/80">
                @foreach($diffResult->fieldDiffs as $field => $data)
                    <div class="p-3.5 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] text-[11px] font-bold">
                                {{ $field }}
                            </span>
                        </div>

                        @if(isset($diffResult->textDiffs[$field]))
                            <!-- Line-by-line diff -->
                            <div class="bg-[color:var(--admin-surface-raised)] p-2.5 rounded border border-[color:var(--admin-border)]/80 space-y-0.5 overflow-x-auto text-[11px] leading-relaxed max-h-60 overflow-y-auto">
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
                                        <div class="text-[color:var(--admin-muted)] px-2 py-0.5 flex items-start gap-2">
                                            <span class="select-none text-[color:var(--admin-muted)]">&nbsp;</span>
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
