import assert from 'node:assert/strict';
import test from 'node:test';
import { createAiWorkbench } from '../../modules/AiAssistant/Resources/js/workbench.js';
import { generatedMediaBlock, mediaBlockActions } from '../../modules/AiAssistant/Resources/js/media-blocks.js';

test('unchecked image generation reaches the canonical request as false', async () => {
    let payload;
    const state = createAiWorkbench();
    Object.assign(state.orchestratorForm, { entity_type: 'page', topic: 'Topic', generate_image: false });
    state.requestGeneration = async value => { payload = value; return {}; };
    await state.orchestrateEntity();
    assert.equal(payload.generate_image, false);
    assert.equal(payload.generate_images, undefined);
});
test('saving orchestration preserves sections, media and generated metadata', async t => {
    let payload;
    t.mock.method(globalThis, 'fetch', async (_, options) => { payload = JSON.parse(options.body); return { json: async () => ({ success: false }) }; });
    const state = createAiWorkbench();
    state.orchestratorResult = { entity_type: 'page', title: 'Title', slug: 'title', summary: 'Summary', content: 'Body', featured_image_url: '/image.webp', meta_title: 'SEO', meta_description: 'Description', sections: [{ type: 'hero', title: 'Hero' }], resolved_context: { locale: 'en', market: 'uk' } };
    await state.saveOrchestratedEntity();
    assert.deepEqual(payload.sections, state.orchestratorResult.sections);
    assert.equal(payload.fields.featured_image, '/image.webp');
    assert.equal(payload.fields.slug, 'title');
    assert.equal(payload.fields.meta_title, 'SEO');
    assert.equal(payload.fields.meta_description, 'Description');
    assert.equal(payload.locale, 'en');
    assert.equal(payload.market, 'uk');
});
for (const [action, tab, field] of [['image','media','mediaResult'], ['video','media','mediaResult'], ['humanize','refine','refineResult'], ['page_element','page','pageResult'], ['orchestrate_multimodal','orchestrator','orchestratorResult']]) {
    test(`resuming ${action} sends the authorized result to ${tab}`, async t => {
        t.mock.method(globalThis, 'setTimeout', callback => callback());
        const data = { marker: action };
        t.mock.method(globalThis, 'fetch', async () => ({ json: async () => ({ success: true, status: 'completed', action, data }) }));
        globalThis.localStorage = { removeItem() {} };
        const state = createAiWorkbench({ statusUrl: '/tasks/__TASK__' });
        await state.resumeTask('task-id');
        assert.equal(state.currentTab, tab);
        assert.deepEqual(state[field], data);
        if (tab === 'media') assert.equal(state.mediaForm.mode, action);
        if (tab !== 'page') assert.equal(state.pageResult, null);
        delete globalThis.localStorage;
    });
}
test('generated media uses current available definitions and current field names', () => {
    const options = [{ type: 'hero' }, { type: 'video' }];
    assert.deepEqual(generatedMediaBlock(options, 'image', '/image.webp', 'Caption'), { type: 'hero', title: 'Caption', content: '', badge: '', cta_text: '', cta_url: '', secondary_cta_text: '', secondary_cta_url: '', image_url: '/image.webp', video_url: '' });
    const video = generatedMediaBlock(options, 'hero-video', '/video.mp4', 'Heading');
    assert.equal(video.title, 'Heading');
    assert.equal(video.video_url, '/video.mp4');
    assert.equal(video.headline, undefined);
    assert.equal(video.video_background, undefined);
    assert.equal(generatedMediaBlock([], 'image', '/image.webp'), null);
});
test('unavailable media blocks leave the modal open and explain the failure', () => {
    const state = { ...mediaBlockActions, availableBlocks: [], canApplyContext: () => true, unavailableBlockMessage: 'Unavailable', closeModal() { throw new Error('must remain open'); } };
    state.insertImageBlock('/image.webp');
    assert.equal(state.errorMessage, 'Unavailable');
});
