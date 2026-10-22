/** Available types come from the shared server registry, never from invented aliases. */
export function generatedMediaBlock(availableBlocks, kind, url, title = '') {
    const type = kind === 'video' ? 'video' : 'hero';
    if (!availableBlocks.some(block => block.type === type)) return null;
    if (kind === 'video') return { type, video_url: url, title, autoplay: false, loop: false, muted: false, controls: true };
    return { type, title, content: '', badge: '', cta_text: '', cta_url: '', secondary_cta_text: '', secondary_cta_url: '', image_url: kind === 'image' ? url : '', video_url: kind === 'hero-video' ? url : '' };
}

export const mediaBlockActions = {
    canInsertMedia(kind) {
        return !!generatedMediaBlock(this.availableBlocks, kind, '');
    },
    insertGeneratedMedia(kind, url, title) {
        if (!this.canApplyContext()) return;
        const block = generatedMediaBlock(this.availableBlocks, kind, url, title);
        if (!block) {
            this.errorMessage = this.unavailableBlockMessage;
            return;
        }
        window.dispatchEvent(new CustomEvent('cms-insert-block', { detail: { block } }));
        this.closeModal();
    },
    insertImageBlock(url, caption = '') {
        this.insertGeneratedMedia('image', url, caption);
    },
    insertVideoBlock(url, title = '') {
        this.insertGeneratedMedia('video', url, title);
    },
    insertHeroVideo(url, title = '') {
        this.insertGeneratedMedia('hero-video', url, title || this.entityTitle() || '');
    },
};
