import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../modules/AiAssistant/Resources/views/admin/partials/entity_translation.blade.php', import.meta.url), 'utf8');
const script = source.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
const events = [];
const window = { cmsContext: { marketIds: ['2'], activeLocales: ['es', 'en'] }, dispatchEvent: e => events.push(e) };
runInNewContext(script, { window, CustomEvent: class { constructor(type, value) { this.type = type; this.detail = value.detail; } }, document: {} });
const make = () => {
    events.length = 0;
    const state = window.cmsEntityTranslationComponent({ fields: { title: { input: 'translations[{locale}][name]' }, slug: { input: 'translations[{locale}][slug]' } }, messages: { stale: 'changed', replace: 'replace', invalid: 'invalid' } });
    state.form = {};
    state.sourceLocale = 'es'; state.targetLocale = 'en';
    state.capture = () => ({ source: { fields: { title: 'Hola' }, sections: [{ id: 'sec_1', type: 'hero', title: 'Hola' }] }, target: { fields: { title: '' }, sections: [] }, markets: [2] });
    state.snapshot = JSON.stringify(state.capture());
    state.result = { source_locale: 'es', target_locale: 'en', fields: { title: 'Hello', slug: 'unsafe', price: 99 }, sections: [{ id: 'sec_1', type: 'hero', title: 'Hello' }] };
    state.show = true;
    return state;
};

test('translation applies only schema text fields and the explicit target locale', () => {
    const state = make(); state.apply();
    assert.equal(events.length, 2);
    assert.equal(events[0].type, 'cms-translation-apply');
    assert.equal(events[0].detail.targetLocale, 'en');
    assert.deepEqual(JSON.parse(JSON.stringify(events[0].detail.values)), { name: 'Hello' });
    assert.equal(events[1].type, 'cms-translation-blocks');
    assert.equal(events[1].detail.blocks[0].id, 'sec_1');
});

test('source, target or market changes prevent applying the generated proposal', () => {
    for (const change of ['source', 'target', 'markets']) {
        const state = make(); const capture = state.capture();
        capture[change] = change === 'markets' ? [3] : { fields: { title: 'Edited' }, sections: [] };
        state.capture = () => capture; state.apply();
        assert.equal(events.length, 0); assert.equal(state.error, 'changed');
    }
});

test('existing target requires explicit review and cannot accept another locale result', () => {
    const state = make(); state.capture = () => ({ source: {}, target: { fields: { title: 'Existing' }, sections: [] }, markets: [2] });
    state.snapshot = JSON.stringify(state.capture()); state.apply();
    assert.equal(events.length, 0); assert.equal(state.error, 'replace');
    state.replaceReviewed = true; state.result.target_locale = 'fr'; state.apply();
    assert.equal(events.length, 0); assert.equal(state.error, 'invalid');
});

test('translation does not submit, publish or persist an entity', () => {
    const state = make(); state.form.submit = () => assert.fail('Must review and save normally');
    state.apply(); assert.equal(state.show, false);
});

test('translation script uses the existing CSP nonce', () => {
    assert.match(source, /<script nonce="\{\{ \$cspNonce/);
});

test('page, post and product forms apply the target without changing its slug or the source', () => {
    for (const path of ['pages', 'blog/posts', 'shop/products']) {
        const form = readFileSync(new URL(`../../resources/views/admin/${path}/_form.blade.php`, import.meta.url), 'utf8');
        const expression = form.match(/x-on:cms-translation-apply\.window="([^"]+)"/)[1];
        const translations = { es: { title: 'Origen', slug: 'origen' }, en: { title: 'Old', slug: 'existing' } };
        const element = {};
        let switched;
        const apply = detail => Function('translations', 'allLanguages', '$event', '$el', 'switchLocale', expression)(translations, ['es', 'en'], { detail }, element, locale => { switched = locale; });
        apply({ form: {}, targetLocale: 'en', values: { title: 'Ignored' } });
        assert.equal(translations.en.title, 'Old');
        apply({ form: element, targetLocale: 'en', values: { title: 'Translated' } });
        assert.equal(translations.en.title, 'Translated');
        assert.equal(translations.en.slug, 'existing');
        assert.equal(translations.es.title, 'Origen');
        assert.equal(switched, 'en');
    }
});

test('block manager applies translated nested structure only to its named locale and form', () => {
    const manager = readFileSync(new URL('../../resources/views/components/admin/block-manager.blade.php', import.meta.url), 'utf8');
    const handler = manager.match(/addEventListener\('cms-translation-blocks', \(event\) => \{([\s\S]*?)\n                \}\);/)[1];
    const form = {};
    const state = { $el: { closest: () => form }, languages: [{ code: 'es' }, { code: 'en' }], activeLocale: 'es', sectionsByLocale: { es: [{ id: 'original' }] }, syncBlocksToLocale() {} };
    const blocks = [{ id: 'sec_1', columns: [{ id: 'col_1', blocks: [{ id: 'block_1', title: 'Hello' }] }] }];
    const apply = detail => Function('event', handler).call(state, { detail });
    apply({ form: {}, targetLocale: 'en', blocks });
    assert.equal(state.sectionsByLocale.en, undefined);
    apply({ form, targetLocale: 'en', blocks });
    assert.deepEqual(state.sectionsByLocale.es, [{ id: 'original' }]);
    assert.deepEqual(state.sectionsByLocale.en, blocks);
    assert.notEqual(state.sectionsByLocale.en, blocks);
});
