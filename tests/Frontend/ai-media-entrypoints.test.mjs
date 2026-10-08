import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
const read = path => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const bridge = read('modules/AiAssistant/Resources/views/admin/partials/copilot_bridge.blade.php');
const method = name => {
    const match = bridge.match(new RegExp(`        ${name}\\([^)]*\\) \\{([\\s\\S]*?)\\n        \\},`));
    assert.ok(match, `${name} must be available`);
    return match[1].replace(/@js\(__\('[^']+'\)\)/g, '"translated"');
};
const call = (name, state, globals) => Function(...Object.keys(globals), `return function() {${method(name)}}`)(...Object.values(globals)).call(state);

test('programmatic media mode opens image generation bound to the requested target', () => {
    assert.match(bridge, /options\.mode === 'image'/);
    const state = { openCopilotModal() { this.mode = 'text'; }, switchMode(mode) { this.mode = mode; }, activeTarget: {} };
    call('openMediaCopilot', state, { mode: 'image', options: { mediaTarget: 'product_featured' } });
    assert.equal(state.mode, 'image');
    assert.equal(state.mediaTarget, 'product_featured');
    assert.equal(state.activeTarget, null);
});

test('generated image fills only the media picker that requested it', () => {
    const events = [];
    const state = { mediaTarget: 'obj_media_123', generatedResult: { url: '/storage/generated.webp', title: 'Cover' }, canApplyContext: () => true, closeModal() { this.closed = true; } };
    call('applyMediaTarget', state, { window: { dispatchEvent(e) { events.push(e); } }, CustomEvent: class { constructor(type, config) { this.type = type; this.detail = config.detail; } } });
    assert.equal(events.length, 1);
    assert.equal(events[0].type, 'file-selected');
    assert.equal(events[0].detail.target, 'obj_media_123');
    assert.equal(events[0].detail.file.url, '/storage/generated.webp');
    assert.equal(state.closed, true);
});

test('media application refuses results from a changed editor context', () => {
    let dispatched = false;
    const state = { mediaTarget: 'cover', generatedResult: { url: '/cover.webp' }, canApplyContext: () => false };
    call('applyMediaTarget', state, { window: { dispatchEvent() { dispatched = true; } } });
    assert.equal(dispatched, false);
});

test('a module can apply a generated image to its explicit URL input without overwriting the cover', () => {
    const events = [];
    const input = { value: '', isConnected: true, dispatchEvent(e) { events.push(e.type); } };
    const state = { mediaInputTarget: input, generatedResult: { url: '/specific.webp' }, canApplyContext: () => true, closeModal() {} };
    call('applyMediaTarget', state, { Event });
    assert.equal(input.value, '/specific.webp');
    assert.deepEqual(events, ['input', 'change']);
});

test('cover fields update Alpine state when the reviewed generated image is selected', () => {
    for (const path of ['resources/views/admin/blog/posts/_form.blade.php', 'resources/views/admin/shop/products/_form.blade.php']) {
        const template = read(path);
        assert.match(template, /@cms-set-featured-image\.window="featuredImage = \$event\.detail\.url"/);
        assert.match(template, /CmsAssistant\.open\(\{ mode: 'image', featured: true \}\)/);
    }
});

test('the universal media picker opens generation with its original destination', () => {
    const picker = read('resources/views/components/admin/filemanager-modal.blade.php');
    assert.match(picker, /mode: 'image', mediaTarget: targetField/);
});

test('media triggers become visible when the module initializes after the entity form', () => {
    assert.ok(bridge.includes("new CustomEvent('cms-assistant-ready')"));
    for (const path of ['resources/views/admin/blog/posts/_form.blade.php', 'resources/views/admin/shop/products/_form.blade.php', 'resources/views/components/admin/filemanager-modal.blade.php']) {
        assert.ok(read(path).includes('@cms-assistant-ready.window="aiAvailable = true"'));
    }
});

test('passwords, numeric data, and image URL inputs cannot receive generated prose', () => {
    for (const type of ['password', 'number', 'email', 'url']) {
        assert.equal(call('isAssistableElement', {}, { el: { tagName: 'INPUT', getAttribute: key => key === 'type' ? type : '' } }), false, type);
    }
    assert.equal(call('isAssistableElement', {}, { el: { tagName: 'TEXTAREA' } }), true);
    assert.equal(call('isAssistableElement', {}, { el: { tagName: 'INPUT', getAttribute: key => key === 'type' ? 'text' : null } }), true);
});

test('typing instructions inside the assistant never steals the destination block field', () => {
    assert.equal(call('isAssistableElement', {}, { el: { tagName: 'TEXTAREA', closest: () => ({}) } }), false);
    assert.equal(call('isAssistableElement', {}, { el: { tagName: 'TEXTAREA', readOnly: true } }), false);
    assert.equal(call('isAssistableElement', {}, { el: { tagName: 'INPUT', getAttribute: key => key === 'x-model' ? 'block.image_url' : null } }), false);
});

test('SEO includes active-language nested block text without mixing translations', () => {
    const state = { detectLocale: () => 'en' };
    const sections = { es: [{ type: 'text', content: 'Texto español' }], en: [{ type: 'columns', columns: [{ blocks: [{ type: 'text', content: 'English nested block' }] }] }] };
    const content = call('entityContent', state, { document: { querySelectorAll: () => [], querySelector: () => ({ value: JSON.stringify(sections) }) } });
    assert.equal(content, 'English nested block');
});
