import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const seo = readFileSync(new URL('../../resources/views/components/admin/seo-meta-box.blade.php', import.meta.url), 'utf8');
const gallery = readFileSync(new URL('../../resources/views/admin/galleries/_form.blade.php', import.meta.url), 'utf8');
const post = readFileSync(new URL('../../resources/views/admin/blog/posts/_form.blade.php', import.meta.url), 'utf8');

for (const [entity, template, body] of [['post', post, 'summary'], ['gallery', gallery, 'description']]) {
    test(`${entity} submits content from both language tabs`, () => {
        const hidden = template.split('<template x-for="code in allLanguages"')[1].split('</template>')[0];
        const names = [...hidden.matchAll(/:name="([^"]+)"/g)].map(match => match[1]);
        const fields = new Set();
        for (const code of ['es', 'en']) {
            for (const expression of names) fields.add(Function('code', `return ${expression}`)(code));
        }
        for (const code of ['es', 'en']) {
            for (const field of ['title', 'slug', body]) assert.ok(fields.has(`translations[${code}][${field}]`));
        }
    });
}

test('inactive language SEO inputs submit inside the translated request contract', () => {
    const hidden = seo.split('<template x-for="code in languages"')[1].split('</template>')[0];
    const names = [...hidden.matchAll(/:name="([^"]+)"/g)].map(match => match[1]);
    const fields = new Map();
    for (const code of ['es', 'en']) {
        for (const expression of names) {
            fields.set(Function('code', `return ${expression}`)(code), `${code} value`);
        }
    }
    assert.equal(fields.get('translations[es][meta_title]'), 'es value');
    assert.equal(fields.get('translations[es][meta_description]'), 'es value');
    assert.equal(fields.get('translations[en][meta_title]'), 'en value');
    assert.equal(fields.get('translations[en][schema_type]'), 'en value');
});

test('adding a gallery image preserves its saved title, alt and caption', () => {
    const method = gallery.match(/openFileManagerForNewItem\(\) \{([\s\S]*?)\n    \},/)[1];
    let listener;
    let target;
    const window = {
        addEventListener(name, callback) { listener = callback; },
        dispatchEvent(event) { target = event.detail.target; },
    };
    const CustomEvent = class { constructor(name, data) { this.detail = data.detail; } };
    const state = { items: [] };
    Function('window', 'CustomEvent', method).call(state, window, CustomEvent);
    listener({ detail: { target, file: { url: '/uploads/qa.webp', name: 'Old name', title: 'Saved title', alt_text: 'Saved descriptive alt', caption: 'Saved caption' } } });
    assert.equal(state.items[0].title, 'Saved title');
    assert.equal(state.items[0].alt_text, 'Saved descriptive alt');
    assert.equal(state.items[0].caption, 'Saved caption');
});
