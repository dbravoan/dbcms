{{-- Injected into the block manager toolbar via the generic 'block_manager_actions' stack --}}
<button type="button"
        @click="window.dispatchEvent(new CustomEvent('cms-open-ai-blocks', { detail: { label: @js(__('admin.ai.block_manager_context')) } }))"
        class="px-2.5 py-1.5 rounded bg-[color:var(--admin-accent)]/20 hover:bg-[color:var(--admin-accent)]/30 border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] hover:text-[color:var(--admin-text)] text-[11px] font-bold flex items-center gap-1.5 transition-colors"
        title="{{ __('admin.ai.generate_blocks_title') }}">
    <i class="fa-solid fa-wand-magic-sparkles"></i>
    <span class="hidden sm:inline">{{ __('admin.ai.generate_blocks_button') }}</span>
</button>
