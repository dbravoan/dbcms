{{-- Injected into the SEO/GEO block header via the generic 'seo_block_actions' stack --}}
<button type="button"
        @click="window.CmsAssistant && window.CmsAssistant.open({ mode: 'seo' })"
        class="px-2.5 py-1 rounded-lg bg-[color:var(--admin-accent)]/10 hover:bg-[color:var(--admin-accent)]/20 border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] text-[10px] font-bold flex items-center gap-1.5 transition-colors"
        title="{{ __('admin.ai.generate_seo_title') }}">
    <i class="fa-solid fa-wand-magic-sparkles"></i>
    <span>{{ __('admin.ai.generate_seo_button') }}</span>
</button>
