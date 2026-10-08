@php
    /** @var \Illuminate\Database\Eloquent\Model $entity */
    $status = $audit?->status ?? ($entityDescription['is_published'] ? 'published' : 'draft');
    $isPending = $status === 'pending_review';
    $isRejected = $status === 'rejected';
    $statusEnum = \Modules\ContentAudit\Enums\AuditStatus::tryFrom($status) ?? \Modules\ContentAudit\Enums\AuditStatus::DRAFT;
@endphp

<div class="bg-[#141414] border border-gray-800 rounded-xl p-4 font-mono space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-gray-800">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-clipboard-check text-[#00FF99]"></i>
            <span class="text-xs font-bold uppercase tracking-wider text-white">{{ __('admin.content_audit.status_title') }}</span>
        </div>
        <span class="px-2.5 py-1 rounded text-[10px] font-bold inline-flex items-center gap-1.5 {{ $statusEnum->badgeClass() }}">
            <i class="fa-solid {{ $statusEnum->icon() }} text-[10px]"></i>
            {{ $statusEnum->label() }}
        </span>
    </div>

    @if($isRejected && !empty($audit?->rejection_reason))
        <div class="p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-xs space-y-1">
            <div class="flex items-center gap-1.5 text-red-400 font-bold text-[11px]">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>{{ __('admin.content_audit.auditor_notes') }}</span>
            </div>
            <p class="text-gray-300 text-[11px] leading-relaxed pl-4">
                {{ $audit->rejection_reason }}
            </p>
        </div>
    @endif

    @if($isPending)
        <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg text-xs space-y-1">
            <div class="flex items-center gap-1.5 text-amber-400 font-bold text-[11px]">
                <i class="fa-solid fa-clock"></i>
                <span>{{ __('admin.content_audit.pending_title') }}</span>
            </div>
            <p class="text-gray-300 text-[11px] leading-relaxed pl-4">
                Enviado a revisión el {{ $audit->submitted_at?->format('d/m/Y H:i') }}. Los auditores han sido notificados.
            </p>
        </div>
    @endif

    <div class="pt-2 flex flex-wrap items-center gap-2">
        @if(!$canPublish)
            <!-- Restricted Author Actions -->
            <button type="submit" name="audit_action" value="draft"
                    class="px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 rounded text-xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-floppy-disk text-gray-400"></i>
                <span>{{ __('admin.content_audit.save_draft') }}</span>
            </button>

            <button type="submit" name="audit_action" value="submit_for_review"
                    class="px-3.5 py-1.5 bg-[#00FF99]/15 hover:bg-[#00FF99] border border-[#00FF99] text-[#00FF99] hover:text-black font-bold rounded text-xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-paper-plane"></i>
                <span>{{ __('admin.content_audit.submit_review') }}</span>
            </button>
        @else
            <!-- Auditor / Approver Actions -->
            <a href="{{ route('admin.audit.review', ['type' => $entityType, 'id' => $entity->id]) }}"
               class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-white rounded text-xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-magnifying-glass text-[#00FF99]"></i>
                <span>{{ __('admin.content_audit.review_room') }}</span>
            </a>

            @if($isPending)
                <a href="{{ route('admin.audit.review', ['type' => $entityType, 'id' => $entity->id]) }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded text-xs transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-check"></i>
                    <span>{{ __('admin.content_audit.approve_publication') }}</span>
                </a>
            @endif
        @endif

        <a href="{{ route('admin.audit.index', ['tab' => 'changelog']) }}"
           class="px-2.5 py-1.5 text-gray-500 hover:text-gray-300 text-xs transition-all ml-auto flex items-center gap-1">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>{{ __('admin.content_audit.history') }}</span>
        </a>
    </div>
</div>
